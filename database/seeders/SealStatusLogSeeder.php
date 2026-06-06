<?php

namespace Database\Seeders;

use App\Enums\SealStatus;
use App\Enums\SepioSealStatus;
use App\Models\Seal;
use App\Models\SealStatusLog;
use Illuminate\Database\Seeder;

/**
 * Generates a synthetic scan history for seals that are in-transit or used.
 *
 * Each scanned seal gets 1-3 status log entries that look like real
 * MarineTraffic / Sepio scan events:
 *   - 'unknown' status shortly after the seal is dispatched
 *   - 'valid' status when scanned at a port (origin or destination)
 *   - 'tampered' status for ~10% of in-transit seals (anomaly exercise data)
 *
 * Lat/long values are deterministic but randomised per seal so the map
 * UI has something to plot.
 *
 * Idempotent: skips seals that already have any log entry.
 */
class SealStatusLogSeeder extends Seeder
{
    public function run(): void
    {
        $total = 0;
        $now = now();

        // Seal has a TenantScope global; seeder runs as platform admin so
        // we need to bypass the scope to see all seals across customers.
        Seal::with('trip')->withoutGlobalScopes()->chunk(100, function ($seals) use (&$total, $now) {
            foreach ($seals as $seal) {
                if (SealStatusLog::where('seal_id', $seal->id)->exists()) {
                    continue;
                }

                $logs = [];

                if ($seal->status === SealStatus::InInventory) {
                    // 50% of in-inventory seals have one initial 'unknown'
                    // scan from when they were first registered.
                    if (mt_rand(0, 1) === 0) {
                        $logs[] = $this->buildLog(
                            $seal,
                            SepioSealStatus::Unknown->value,
                            $seal->created_at ?: $now,
                            'Initial registration scan.',
                        );
                    }
                } elseif (in_array($seal->status, [
                    SealStatus::Assigned,
                    SealStatus::InTransit,
                    SealStatus::Used,
                ], true)) {
                    $baseTime = $seal->trip?->trip_start_time ?: ($seal->created_at ?: $now);

                    // 1. initial unknown scan at dispatch
                    $logs[] = $this->buildLog(
                        $seal,
                        SepioSealStatus::Unknown->value,
                        $baseTime->copy()->addMinutes(15),
                        'Initial scan at dispatch.',
                    );

                    // 2. valid scan at the origin port
                    $logs[] = $this->buildLog(
                        $seal,
                        SepioSealStatus::Valid->value,
                        $baseTime->copy()->addHours(2),
                        'Scanned at origin port.',
                    );

                    // 3. ~10% of in-transit seals are tampered
                    if ($seal->status === SealStatus::InTransit && mt_rand(1, 10) === 1) {
                        $logs[] = $this->buildLog(
                            $seal,
                            SepioSealStatus::Tampered->value,
                            $baseTime->copy()->addHours(6),
                            'Anomaly detected mid-transit.',
                        );
                    } elseif ($seal->status === SealStatus::Used) {
                        // Used seals: final valid scan at the destination port.
                        $logs[] = $this->buildLog(
                            $seal,
                            SepioSealStatus::Valid->value,
                            ($seal->trip?->trip_end_time ?: $baseTime)->copy()->addHours(8),
                            'Scanned at destination port.',
                        );
                    }
                }

                if (!empty($logs)) {
                    SealStatusLog::insert($logs);
                    $total += count($logs);
                }
            }
        });

        $this->command?->info("  SealStatusLogSeeder: {$total} scan logs seeded.");
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLog(Seal $seal, string $status, \DateTimeInterface $checkedAt, string $message): array
    {
        // Deterministic-but-varied lat/lng from the seal id hash so
        // the map UI gets a spread of points rather than one stack.
        $h = hexdec(substr(md5($seal->id . $status), 0, 8));
        $lat = 18.5 + (($h % 1000) / 1000.0) * 6.0;   // ~18.5 to ~24.5
        $lng = 72.0 + ((($h >> 10) % 1000) / 1000.0) * 5.0; // ~72 to ~77

        return [
            'customer_id' => $seal->customer_id,
            'seal_id' => $seal->id,
            'trip_id' => $seal->trip_id,
            'status' => $status,
            'checked_at' => $checkedAt,
            'scan_location' => 'Mumbai Port (INBOM1)',
            'scanned_lat' => $lat,
            'scanned_lng' => $lng,
            'scanned_by' => 'system',
            'raw_response' => json_encode([
                'message' => $message,
                'seal_number' => $seal->seal_number,
                'status' => $status,
            ]),
        ];
    }
}
