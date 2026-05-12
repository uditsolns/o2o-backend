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
use Illuminate\Support\Facades\Log;

class SepioSealAllocationPollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(SepioClient $client, SealService $sealService): void
    {
        // Watch orders in any Sepio-active status — allocation can arrive at any of these.
        // MfgCompleted is included so that if the status sync job ran first and set that
        // status, we can still ingest seals and then finalize the order here.
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

        // Group by customer to minimise token fetches
        $orders->groupBy('customer_id')->each(function ($customerOrders) use ($client, $sealService) {
            $customer = $customerOrders->first()->customer;
            try {
                $this->pollForCustomer($client, $sealService, $customer, $customerOrders);
            } catch (\Throwable $e) {
                Log::error('SepioSealAllocationPollJob: customer poll failed', [
                    'customer_id' => $customer->id, 'error' => $e->getMessage(),
                ]);
            }
        });
    }

    private function pollForCustomer(
        SepioClient $client,
        SealService $sealService,
                    $customer,
                    $orders
    ): void
    {
        // Sepio allocation pull supports max 2-day window
        $end = now();
        $start = $end->copy()->subDays(2);
        $response = $client->postAs($customer, '/api/v1/seal/seal-allocation/pull', [
            'company_id' => $customer->sepio_company_id,
            'start_datetime' => $start->format('Y-m-d H:i:s.v'),
            'end_datetime' => $end->format('Y-m-d H:i:s.v'),
        ]);

        if ($response->failed()) {
            Log::error('Sepio seal allocation poll failed', [
                'customer_id' => $customer->id,
                'response' => $response->json(),
            ]);
            return;
        }

        $allocations = $response->json('data', []);

        if (empty($allocations)) return;

        // Index allocations by Sepio orderId
        $allocationMap = collect($allocations)->keyBy('orderId');

        foreach ($orders as $order) {
            try {
                $allocation = $allocationMap[$order->sepio_order_id] ?? null;
                if (!$allocation) continue;
                $this->processAllocation($sealService, $order, $allocation);
            } catch (\Throwable $e) {
                Log::error('SepioSealAllocationPollJob: order ingest failed', [
                    'order_id' => $order->id, 'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function processAllocation(SealService $sealService, SealOrder $order, array $allocation): void
    {
        // seal_range: "SPPL10009259 - SPPL10009260"
        $range = $allocation['seal_range'] ?? null;
        if (!$range) return;

        $sealNumbers = $this->expandSealRange($range);
        if (empty($sealNumbers)) return;

        if (count($sealNumbers) !== $order->quantity) {
            Log::warning('SepioSealAllocationPollJob: seal count mismatch — skipping until correct range arrives', [
                'order_id'   => $order->id,
                'expected'   => $order->quantity,
                'got'        => count($sealNumbers),
                'seal_range' => $range,
            ]);
            return; // wait for Sepio to correct the range
        }

        // Idempotency: if seals are already ingested for this order, skip insertion
        // but still check whether we can complete the order (handles the case where
        // this job ran before the status sync job advanced to MfgCompleted).
        if ($order->seals()->exists()) {
            Log::info('SepioSealAllocationPollJob: seals already ingested, checking completion', [
                'order_id' => $order->id,
            ]);
            $sealService->completeOrderIfSealsReady($order->fresh());
            return;
        }

        // Ingest seals WITHOUT completing the order.
        // Seals are "not activated" on Sepio's side until the order is fully done.
        // completeOrderIfSealsReady() will finalize the order once both conditions are met:
        //   1) seals ingested (done here)
        //   2) order status = MfgCompleted (done by SepioOrderStatusSyncJob)
        $sealService->ingestFromOrder(
            $order,
            $sealNumbers,
            now()->toISOString(),
            complete: false   // ← do NOT complete yet
        );

        Log::info('SepioSealAllocationPollJob: seals ingested (order not yet completed)', [
            'order_id'   => $order->id,
            'seal_count' => count($sealNumbers),
            'order_status' => $order->status->value,
        ]);

        // If the status sync job already advanced this order to MfgCompleted before
        // seals arrived, we can complete it right now.
        $sealService->completeOrderIfSealsReady($order->fresh());
    }

    /**
     * Expand "SPPL10009259 - SPPL10009260" into ["SPPL10009259", "SPPL10009260"]
     * Sepio seal numbers are SPPL + numeric suffix — expand the numeric range.
     */
    private function expandSealRange(string $range): array
    {
        $parts = array_map('trim', explode(' - ', $range));

        if (count($parts) !== 2) return [$range]; // single seal

        [$from, $to] = $parts;

        // Extract prefix and numeric parts
        preg_match('/^([A-Z]+)(\d+)$/', $from, $fromMatch);
        preg_match('/^([A-Z]+)(\d+)$/', $to, $toMatch);

        if (!$fromMatch || !$toMatch || $fromMatch[1] !== $toMatch[1]) {
            return [$from, $to];
        }

        $prefix = $fromMatch[1];
        $fromNum = (int)$fromMatch[2];
        $toNum = (int)$toMatch[2];
        $padLen = strlen($fromMatch[2]);

        $seals = [];
        for ($i = $fromNum; $i <= $toNum; $i++) {
            $seals[] = $prefix . str_pad($i, $padLen, '0', STR_PAD_LEFT);
        }

        return $seals;
    }
}
