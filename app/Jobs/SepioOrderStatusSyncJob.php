<?php

namespace App\Jobs;

use App\Enums\SealOrderStatus;
use App\Models\SealOrder;
use App\Services\SealService;
use App\Services\Sepio\SepioClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;

class SepioOrderStatusSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    /**
     * Forward-only progression for normal status transitions.
     * MfgRejected is terminal and handled separately.
     * Completed sits after MfgCompleted — we skip straight to it when seals are ready.
     */
    private const PROGRESSION = [
        'mfg_pending',
        'order_placed',
        'in_progress',
        'in_transit',
        'mfg_completed', // Sepio finished — seals may or may not be ingested yet
        'completed',     // Seals ingested + Sepio done → fully usable
    ];

    public function handle(SepioClient $client, SealService $sealService): void
    {
        // All orders in Sepio's hands that haven't reached a terminal state.
        // MfgCompleted is included: the job will complete such orders once
        // seals are confirmed ingested.
        $orders = SealOrder::with('customer')
            ->whereIn('status', [
                SealOrderStatus::MfgPending,
                SealOrderStatus::OrderPlaced,
                SealOrderStatus::InProgress,
                SealOrderStatus::InTransit,
                SealOrderStatus::MfgCompleted,
            ])
            ->whereNotNull('sepio_order_id')
            ->get();

        if ($orders->isEmpty()) return;

        $orders->groupBy('customer_id')->each(function (Collection $customerOrders) use ($client, $sealService) {
            $customer = $customerOrders->first()->customer;
            try {
                $this->syncForCustomer($client, $sealService, $customer, $customerOrders);
            } catch (\Throwable $e) {
                Log::error('SepioOrderStatusSyncJob: customer sync failed', [
                    'customer_id' => $customer->id, 'error' => $e->getMessage(),
                ]);
            }
        });
    }

    private function syncForCustomer(SepioClient $client, SealService $sealService, $customer, Collection $orders): void
    {
        $response = $client->getAs($customer, '/companyAdmin/listplacedorder', [
            'companyId' => $customer->sepio_company_id,
            'pageNo'    => 0,
        ]);

        $sepioMap = collect();

        if ($response->successful()) {
            $sepioMap = collect($response->json('data', []))
                ->keyBy(fn($o) => (string) ($o['orderId'] ?? ''));
        } else {
            Log::warning('SepioOrderStatusSyncJob: listplacedorder failed', [
                'customer_id' => $customer->id,
                'status'      => $response->status(),
            ]);
        }

        foreach ($orders as $order) {
            try {
                $sepioOrder = $sepioMap[(string) $order->sepio_order_id] ?? null;
                if (!$sepioOrder) {
                    $sepioOrder = $this->fetchSingleOrder($client, $customer, $order->sepio_order_id);
                }
                if (!$sepioOrder) continue;
                $this->applyStatus($sealService, $order, $sepioOrder);
            } catch (\Throwable $e) {
                Log::error('SepioOrderStatusSyncJob: order sync failed', [
                    'order_id' => $order->id, 'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function fetchSingleOrder(SepioClient $client, $customer, string $sepioOrderId): ?array
    {
        $response = $client->getAs($customer, '/companyAdmin/filterorderlistcompany', [
            'companyId'        => $customer->sepio_company_id,
            'pageNo'           => 0,
            'orderId'          => $sepioOrderId,
            'invoiceNo'        => '',
            'dispatchDateFrom' => '',
            'dispatchDateTo'   => '',
        ]);

        if ($response->failed()) return null;

        return collect($response->json('data', []))->first(
            fn($o) => (string) ($o['orderId'] ?? '') === $sepioOrderId
        );
    }

    private function applyStatus(SealService $sealService, SealOrder $order, array $sepioOrder): void
    {
        $rawStatus = $sepioOrder['orderStatusType'] ?? $sepioOrder['status'] ?? null;

        if (!$rawStatus) {
            Log::debug('SepioOrderStatusSyncJob: no status field in response', [
                'order_id'    => $order->id,
                'sepio_order' => $sepioOrder,
            ]);
            return;
        }

        $newStatus = $this->mapSepioStatus($rawStatus);
        if (!$newStatus) return;

        // Cancellation is terminal — always apply regardless of current status
        if ($newStatus === SealOrderStatus::MfgRejected) {
            if ($order->status !== SealOrderStatus::MfgRejected) {
                $order->update(['status' => $newStatus]);
                Log::warning('SepioOrderStatusSyncJob: order cancelled by Sepio', [
                    'order_id'       => $order->id,
                    'sepio_order_id' => $order->sepio_order_id,
                ]);
            }
            return;
        }

        if ($this->isRegressionOrSame($order->status, $newStatus)) return;

        $orderUpdates = ['status' => $newStatus];

        // Stamp dispatch time when transitioning to InTransit
        if ($newStatus === SealOrderStatus::InTransit && is_null($order->seals_dispatched_at)) {
            $orderUpdates['seals_dispatched_at'] = now();
        }

        $order->update($orderUpdates);

        Log::info('SepioOrderStatusSyncJob: status advanced', [
            'order_id'       => $order->id,
            'sepio_order_id' => $order->sepio_order_id,
            'from'           => $order->status->value,
            'to'             => $newStatus->value,
            'sepio_status'   => $rawStatus,
        ]);

        // When Sepio marks the order as done (MfgCompleted), check whether seals
        // are already ingested by SepioSealAllocationPollJob.  If so, finalize
        // the order to Completed right now so the seals become usable immediately.
        // If seals haven't arrived yet, completeOrderIfSealsReady() is a no-op and
        // the allocation poll job will call it again once seals are ingested.
        if ($newStatus === SealOrderStatus::MfgCompleted) {
            $sealService->completeOrderIfSealsReady($order->fresh());
        }
    }

    /**
     * Sepio status string → our SealOrderStatus enum.
     * Sepio's "completed" maps to MfgCompleted (not our Completed) because seals
     * must also be ingested before we consider the order fully done on our side.
     */
    private function mapSepioStatus(string $sepioStatus): ?SealOrderStatus
    {
        return match (strtolower(trim($sepioStatus))) {
            'placed' => SealOrderStatus::OrderPlaced,
            'in progress' => SealOrderStatus::InProgress,
            'in transit' => SealOrderStatus::InTransit,
            'completed' => SealOrderStatus::MfgCompleted,
            'cancelled' => SealOrderStatus::MfgRejected,
            default => null,
        };
    }

    private function isRegressionOrSame(SealOrderStatus $current, SealOrderStatus $proposed): bool
    {
        $currentIdx = array_search($current->value, self::PROGRESSION);
        $proposedIdx = array_search($proposed->value, self::PROGRESSION);

        if ($currentIdx === false || $proposedIdx === false) return false;

        return $proposedIdx <= $currentIdx;
    }
}
