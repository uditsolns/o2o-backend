<?php

namespace Database\Seeders;

use App\Enums\SealOrderStatus;
use App\Enums\SealStatus;
use App\Enums\SepioSealStatus;
use App\Models\Seal;
use App\Models\SealOrder;
use App\Models\SealStatusLog;
use Illuminate\Database\Seeder;

/**
 * Seeds Seal inventory from Completed orders.
 * Also seeds some SealStatusLog entries to simulate Sepio scan events.
 *
 * Seal statuses represented:
 *   in_inventory | assigned | in_transit | used | tampered | lost
 *
 * Returns all seeded seals so TripSeeder can reference available ones.
 *
 * @return Seal[]
 */
class SealSeeder extends Seeder
{
    public function run(): array
    {
        $completedOrders = SealOrder::where('status', SealOrderStatus::Completed)
            ->with('customer')
            ->get();

        $allSeals = [];
        $logCount = 0;

        foreach ($completedOrders as $order) {
            // Skip if seals already ingested for this order
            if (Seal::where('seal_order_id', $order->id)->exists()) {
                $allSeals = array_merge($allSeals, Seal::where('seal_order_id', $order->id)->get()->all());
                continue;
            }

            $seals = [];
            $prefix = 'SPPL' . str_pad($order->customer_id, 3, '0', STR_PAD_LEFT);
            $startNum = $order->id * 1000;          // unique range per order
            $now = now();

            for ($i = 0; $i < $order->quantity; $i++) {
                $sealNumber = $prefix . str_pad($startNum + $i, 6, '0', STR_PAD_LEFT);

                // Distribute statuses: mostly in_inventory, a few others
                [$status, $sepioStatus] = $this->pickStatus($i, $order->quantity);

                $seals[] = [
                    'customer_id' => $order->customer_id,
                    'seal_order_id' => $order->id,
                    'trip_id' => null,
                    'seal_number' => $sealNumber,
                    'status' => $status->value,
                    'sepio_status' => $sepioStatus->value,
                    'last_scan_at' => $status !== SealStatus::InInventory ? $now->subDays(rand(1, 10)) : null,
                    'delivered_at' => $now->subDays(12)->toDateString(),
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }

            foreach (array_chunk($seals, 500) as $chunk) {
                Seal::insert($chunk);
            }

            // Add scan logs for a subset of seals
            $insertedSeals = Seal::where('seal_order_id', $order->id)
                ->whereNotIn('status', [SealStatus::InInventory->value])
                ->take(10)
                ->get();

            foreach ($insertedSeals as $seal) {
                $scanLocation = $this->scanLocationFor($seal->status->value, $seal->customer_id);

                SealStatusLog::insert([
                    'customer_id' => $seal->customer_id,
                    'seal_id' => $seal->id,
                    'trip_id' => null,
                    'status' => $seal->sepio_status->value,
                    'scan_location' => $scanLocation['name'],
                    'scanned_lat' => $scanLocation['lat'],
                    'scanned_lng' => $scanLocation['lng'],
                    'scanned_by' => 'Sepio Scanner Agent',
                    'raw_response' => json_encode(['sealStatus' => ucfirst($seal->sepio_status->value), 'location' => $scanLocation['name']]),
                    'checked_at' => now()->subDays(rand(1, 10)),
                ]);
            }

            $allSeals = array_merge($allSeals, Seal::where('seal_order_id', $order->id)->get()->all());
        }

        $this->command->info('  SealSeeder: ' . count($allSeals) . " seals, {$logCount} status logs seeded.");

        return $allSeals;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /**
     * Distribute seal statuses roughly:
     *   70% in_inventory  (available for trips)
     *   10% used          (trip completed)
     *    8% assigned      (on active trip)
     *    5% in_transit
     *    4% tampered
     *    3% lost
     */
    private function pickStatus(int $index, int $total): array
    {
        $pct = ($index / $total) * 100;

        if ($pct < 70) return [SealStatus::InInventory, SepioSealStatus::Unknown];
        if ($pct < 80) return [SealStatus::Used, SepioSealStatus::Valid];
        if ($pct < 88) return [SealStatus::Assigned, SepioSealStatus::Valid];
        if ($pct < 93) return [SealStatus::InTransit, SepioSealStatus::Valid];
        if ($pct < 97) return [SealStatus::Tampered, SepioSealStatus::Tampered];
        return [SealStatus::Lost, SepioSealStatus::Unknown];
    }

    private function scanLocationFor(string $status, int $customerId): array
    {
        $locations = [
            ['name' => 'Jawaharlal Nehru Port (INNSA)', 'lat' => 18.9488, 'lng' => 72.9511],
            ['name' => 'Chennai Port Kamarajar (INMAA)', 'lat' => 13.0836, 'lng' => 80.2969],
            ['name' => 'Mundra Port (INMUN)', 'lat' => 22.8381, 'lng' => 69.7032],
            ['name' => 'Jebel Ali Port (AEJEA)', 'lat' => 24.9857, 'lng' => 55.0272],
            ['name' => 'ICD Tughlakabad (INTDL)', 'lat' => 28.5011, 'lng' => 77.2877],
        ];

        return $locations[array_rand($locations)];
    }
}
