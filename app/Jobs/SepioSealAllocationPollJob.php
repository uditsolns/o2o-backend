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
        // Watch all orders that Sepio has in flight or has completed on their end.
        // MfgCompleted is the most likely state to have a seal_range ready,
        // but allocation can appear as early as InTransit.
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

            $this->pollForCustomer($client, $sealService, $customer, $customerOrders);
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
        $response = $client->postAs($customer, '/api/v1/seal/seal-allocation/pull', [
            'company_id' => $customer->sepio_company_id,
            'start_datetime' => now()->subDays(2)->format('Y-m-d H:i:s.v'),
            'end_datetime' => now()->format('Y-m-d H:i:s.v'),
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
            $allocation = $allocationMap[$order->sepio_order_id] ?? null;

            if (!$allocation) continue;

            $this->ingestAllocation($sealService, $order, $allocation);
        }
    }

    private function ingestAllocation(SealService $sealService, SealOrder $order, array $allocation): void
    {
        // seal_range: "SPPL10009259 - SPPL10009260"
        $range = $allocation['seal_range'] ?? null;

        if (!$range) return;

        $sealNumbers = $this->expandSealRange($range);

        if (empty($sealNumbers)) return;

        if (count($sealNumbers) !== $order->quantity) {
            Log::warning('Sepio seal count mismatch', [
                'order_id' => $order->id,
                'expected' => $order->quantity,
                'got' => count($sealNumbers),
                'seal_range' => $range,
            ]);
        }

        try {
            $sealService->ingestFromOrder($order, $sealNumbers, now()->toISOString());

            Log::info('Seals ingested from Sepio allocation', [
                'order_id' => $order->id,
                'from_status' => $order->status->value,
                'seal_count' => count($sealNumbers),
            ]);
        } catch (\Throwable $e) {
            Log::error('Seal ingestion failed', [
                'order_id' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
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
