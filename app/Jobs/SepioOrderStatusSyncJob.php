<?php

namespace App\Jobs;

use App\Enums\SealOrderStatus;
use App\Models\SealOrder;
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
     */
    private const PROGRESSION = [
        'mfg_pending',
        'order_placed',
        'in_progress',
        'in_transit',
        'mfg_completed', // Completed — seals not yet ingested
        'completed',     // Seal numbers received and ingested
    ];

    public function handle(SepioClient $client): void
    {
        // All orders in Sepio's hands that haven't reached a terminal state
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

        $orders->groupBy('customer_id')->each(function (Collection $customerOrders) use ($client) {
            $customer = $customerOrders->first()->customer;
            $this->syncForCustomer($client, $customer, $customerOrders);
        });
    }

    private function syncForCustomer(SepioClient $client, $customer, Collection $orders): void
    {
        $response = $client->getAs($customer, '/companyAdmin/listplacedorder', [
            'companyId' => $customer->sepio_company_id,
            'pageNo' => 0,
        ]);

        $sepioMap = collect();

        if ($response->successful()) {
            $sepioMap = collect($response->json('data', []))
                ->keyBy(fn($o) => (string)($o['orderId'] ?? ''));
        } else {
            Log::warning('SepioOrderStatusSyncJob: listplacedorder failed', [
                'customer_id' => $customer->id,
                'status' => $response->status(),
            ]);
        }

        foreach ($orders as $order) {
            $sepioOrder = $sepioMap[(string)$order->sepio_order_id] ?? null;

            // Fallback for orders not on page 0 (older / high-volume customers)
            if (!$sepioOrder) {
                $sepioOrder = $this->fetchSingleOrder($client, $customer, $order->sepio_order_id);
            }

            if (!$sepioOrder) continue;

            $this->applyStatus($order, $sepioOrder);
        }
    }

    private function fetchSingleOrder(SepioClient $client, $customer, string $sepioOrderId): ?array
    {
        $response = $client->getAs($customer, '/companyAdmin/filterorderlistcompany', [
            'companyId' => $customer->sepio_company_id,
            'pageNo' => 0,
            'orderId' => $sepioOrderId,
            'invoiceNo' => '',
            'dispatchDateFrom' => '',
            'dispatchDateTo' => '',
        ]);

        if ($response->failed()) return null;

        return collect($response->json('data', []))->first(
            fn($o) => (string)($o['orderId'] ?? '') === $sepioOrderId
        );
    }

    private function applyStatus(SealOrder $order, array $sepioOrder): void
    {
        $rawStatus = $sepioOrder['orderStatusType'] ?? $sepioOrder['status'] ?? null;

        if (!$rawStatus) {
            Log::debug('SepioOrderStatusSyncJob: no status field in response', [
                'order_id' => $order->id,
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
                    'order_id' => $order->id,
                    'sepio_order_id' => $order->sepio_order_id,
                ]);
            }
            return;
        }

        if ($this->isRegressionOrSame($order->status, $newStatus)) return;

        $order->update(['status' => $newStatus]);

        Log::info('SepioOrderStatusSyncJob: status advanced', [
            'order_id' => $order->id,
            'sepio_order_id' => $order->sepio_order_id,
            'from' => $order->status->value,
            'to' => $newStatus->value,
            'sepio_status' => $rawStatus,
        ]);
    }

    /**
     * Sepio status string → our SealOrderStatus enum.
     */
    private function mapSepioStatus(string $sepioStatus): ?SealOrderStatus
    {
        return match (strtolower(trim($sepioStatus))) {
            'Placed' => SealOrderStatus::OrderPlaced,
            'In Progress' => SealOrderStatus::InProgress,
            'In Transit' => SealOrderStatus::InTransit,
            'Completed' => SealOrderStatus::MfgCompleted,
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
