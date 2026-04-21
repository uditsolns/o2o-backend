<?php

namespace Database\Seeders;

use App\Enums\CustomerOnboardingStatus;
use App\Enums\TripStatus;
use App\Enums\TripTransportationMode;
use App\Models\Customer;
use App\Models\Trip;
use App\Models\TripTrackingPoint;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class TripTrackingPointSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::where('onboarding_status', CustomerOnboardingStatus::Completed->value)->get();
        $total = 0;

        $trackableStatuses = [
            TripStatus::InTransit,
            TripStatus::AtPort,
            TripStatus::OnVessel,
            TripStatus::InTransshipment,
            TripStatus::VesselArrived,
            TripStatus::Delivered,
            TripStatus::Completed,
        ];

        foreach ($customers as $customer) {
            $trips = Trip::where('customer_id', $customer->id)
                ->whereIn('status', array_map(fn($s) => $s->value, $trackableStatuses))
                ->get();

            foreach ($trips as $trip) {
                if (TripTrackingPoint::where('trip_id', $trip->id)->exists()) {
                    continue;
                }

                $points = $this->generatePoints($trip);

                foreach ($points as $point) {
                    TripTrackingPoint::insert([
                        'trip_id' => $trip->id,
                        'customer_id' => $trip->customer_id,
                        'source' => $point['source'],
                        'lat' => $point['lat'],
                        'lng' => $point['lng'],
                        'speed' => $point['speed'],
                        'heading' => $point['heading'],
                        'accuracy' => $point['accuracy'] ?? null,
                        'location_name' => $point['location_name'] ?? null,
                        'external_id' => $point['external_id'] ?? null,
                        'recorded_at' => $point['recorded_at'],
                        'raw_payload' => json_encode(['seeded' => true]),
                        'created_at' => $point['recorded_at'],
                    ]);
                    $total++;
                }

                // Update last known position on trip
                $last = end($points);
                $trip->updateQuietly([
                    'last_known_lat' => $last['lat'],
                    'last_known_lng' => $last['lng'],
                    'last_known_source' => $last['source'],
                    'last_tracked_at' => $last['recorded_at'],
                ]);
            }
        }

        $this->command->info("  TripTrackingPointSeeder: {$total} tracking points seeded.");
    }

    private function generatePoints(Trip $trip): array
    {
        $isRoad = in_array($trip->transport_mode, [
            TripTransportationMode::Road,
            TripTransportationMode::Multimodal,
        ]);

        $startTime = $trip->trip_start_time ?? now()->subDays(10);
        $pointCount = 8;
        $points = [];

        // Determine lat/lng interpolation bounds
        $startLat = (float)($trip->dispatch_lat ?? fake()->latitude(18, 28));
        $startLng = (float)($trip->dispatch_lng ?? fake()->longitude(72, 88));
        $endLat = (float)($trip->delivery_lat ?? $startLat + fake()->randomFloat(2, 0.5, 3.0));
        $endLng = (float)($trip->delivery_lng ?? $startLng + fake()->randomFloat(2, 0.5, 3.0));

        for ($i = 0; $i < $pointCount; $i++) {
            $fraction = $i / ($pointCount - 1);
            $lat = round($startLat + ($endLat - $startLat) * $fraction + fake()->randomFloat(4, -0.01, 0.01), 7);
            $lng = round($startLng + ($endLng - $startLng) * $fraction + fake()->randomFloat(4, -0.01, 0.01), 7);
            $recordedAt = Carbon::parse($startTime)->addHours($i * 3);

            if ($isRoad) {
                // Alternate between driver_mobile and fast_tag
                if ($i % 3 === 2) {
                    $points[] = [
                        'source' => 'fast_tag',
                        'lat' => $lat,
                        'lng' => $lng,
                        'speed' => rand(40, 80),
                        'heading' => rand(0, 359),
                        'accuracy' => null,
                        'location_name' => 'Toll Plaza ' . ($i + 1),
                        'external_id' => 'FT' . $trip->id . str_pad($i, 4, '0', STR_PAD_LEFT),
                        'recorded_at' => $recordedAt,
                    ];
                } else {
                    $points[] = [
                        'source' => 'driver_mobile',
                        'lat' => $lat,
                        'lng' => $lng,
                        'speed' => rand(30, 90),
                        'heading' => rand(0, 359),
                        'accuracy' => rand(5, 30),
                        'location_name' => null,
                        'external_id' => null,
                        'recorded_at' => $recordedAt,
                    ];
                }
            } else {
                // Sea tracking via AIS
                $points[] = [
                    'source' => 'vessel_ais',
                    'lat' => $lat,
                    'lng' => $lng,
                    'speed' => rand(10, 22),
                    'heading' => rand(0, 359),
                    'accuracy' => null,
                    'location_name' => null,
                    'external_id' => null,
                    'recorded_at' => $recordedAt,
                ];
            }
        }

        return $points;
    }
}
