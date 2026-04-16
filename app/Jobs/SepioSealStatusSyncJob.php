<?php

namespace App\Jobs;

use App\Enums\SealStatus;
use App\Models\Seal;
use App\Services\SealService;
use App\Services\Sepio\SepioClient;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class SepioSealStatusSyncJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(SepioClient $client, SealService $sealService): void
    {
        // Only seals actively on a trip
        $seals = Seal::with('customer')
            ->whereIn('status', [
                SealStatus::Assigned,
                SealStatus::InTransit,
            ])
            ->whereNotNull('trip_id')
            ->get();

        if ($seals->isEmpty()) return;

        // Group by customer to reuse token
        $seals->groupBy('customer_id')->each(function ($customerSeals) use ($client, $sealService) {
            $customer = $customerSeals->first()->customer;
            foreach ($customerSeals as $seal) {
                try {
                    $this->syncSeal($client, $sealService, $customer, $seal);
                } catch (\Throwable $e) {
                    Log::error('SepioSealStatusSyncJob: seal sync failed', [
                        'seal_id' => $seal->id,
                        'seal_number' => $seal->seal_number,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        });
    }

    private function syncSeal(
        SepioClient $client,
        SealService $sealService,
                    $customer,
        Seal        $seal
    ): void
    {
        // Use last_scan_at as from_datetime, fallback to 24h ago
        $fromDatetime = $seal->last_scan_at
            ? $seal->last_scan_at->format('Y-m-d H:i:s.v')
            : now()->subDay()->format('Y-m-d H:i:s.v');

        $response = $client->postAs($customer, '/api/v1/seal/scan-history/pull', [
            'seal_no' => $seal->seal_number,
            'from_datetime' => $fromDatetime,
        ]);

        if ($response->failed()) {
            Log::warning('Sepio seal scan history pull failed', [
                'seal_id' => $seal->id,
                'error' => $client->parseError($response, 'Scan history pull failed.'),
            ]);
            return;
        }

        $scanHistory = $response->json('scan_history', []);

        if (empty($scanHistory)) return;

        // Process each scan entry — most recent last, so last one wins on seal update
        foreach ($scanHistory as $scan) {
            $sealService->syncStatus($seal->fresh(), [
                'status' => $this->mapSealStatus($scan['sealStatus'] ?? ''),
                'location' => $scan['location'] ?? null,
                'lat' => $scan['latitude'] ?? null,
                'lng' => $scan['longitude'] ?? null,
                'scanned_by' => $scan['createdBy'] ?? null,
                'scanned_at' => $scan['createdAt'] ?? now()->toISOString(),
            ]);
        }

        Log::info('Seal scan history synced', [
            'seal_id' => $seal->id,
            'scan_count' => count($scanHistory),
        ]);
    }

    /**
     * Map Sepio sealStatus string → our sepio_status enum value.
     * Sepio returns "Success", "Tampered", "Broken" etc.
     */
    private function mapSealStatus(string $sepioStatus): string
    {
        return match (strtolower($sepioStatus)) {
            'success' => 'valid',
            'tampered' => 'tampered',
            'broken' => 'broken',
            default => 'unknown',
        };
    }
}
