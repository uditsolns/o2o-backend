<?php

namespace Database\Seeders;

use App\Enums\CustomerOnboardingStatus;
use App\Enums\TripTransportationMode;
use App\Models\Customer;
use App\Models\Trip;
use App\Models\TripSegment;
use Illuminate\Database\Seeder;

class TripSegmentSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::where('onboarding_status', CustomerOnboardingStatus::Completed->value)->get();
        $total = 0;

        foreach ($customers as $customer) {
            $trips = Trip::where('customer_id', $customer->id)->get();

            foreach ($trips as $trip) {
                // Skip if already seeded
                if (TripSegment::where('trip_id', $trip->id)->exists()) {
                    continue;
                }

                $segments = $this->segmentsFor($trip);

                foreach ($segments as $seg) {
                    TripSegment::create([
                        ...$seg,
                        'trip_id' => $trip->id,
                        'customer_id' => $trip->customer_id,
                    ]);
                    $total++;
                }
            }
        }

        $this->command->info("  TripSegmentSeeder: {$total} segments seeded.");
    }

    private function segmentsFor(Trip $trip): array
    {
        return match ($trip->transport_mode) {
            TripTransportationMode::Road => $this->roadSegments($trip),
            TripTransportationMode::Sea => $this->seaSegments($trip),
            TripTransportationMode::Multimodal => $this->multimodalSegments($trip),
            default => [],
        };
    }

    private function roadSegments(Trip $trip): array
    {
        return [
            [
                'sequence' => 1,
                'source_name' => $trip->dispatch_location_name ?? $trip->dispatch_city,
                'destination_name' => $trip->delivery_location_name ?? $trip->delivery_city,
                'transport_mode' => 'road',
                'tracking_source' => 'driver_mobile',
                'notes' => 'Direct road delivery.',
            ],
        ];
    }

    private function seaSegments(Trip $trip): array
    {
        return [
            [
                'sequence' => 1,
                'source_name' => $trip->origin_port_name ?? 'Origin Port',
                'destination_name' => $trip->destination_port_name ?? 'Destination Port',
                'transport_mode' => 'sea',
                'tracking_source' => null,
                'notes' => 'Ocean freight leg.',
            ],
        ];
    }

    private function multimodalSegments(Trip $trip): array
    {
        return [
            [
                'sequence' => 1,
                'source_name' => $trip->dispatch_location_name ?? $trip->dispatch_city,
                'destination_name' => $trip->origin_port_name ?? 'Origin Port',
                'transport_mode' => 'road',
                'tracking_source' => 'driver_mobile',
                'notes' => 'Road leg to port.',
            ],
            [
                'sequence' => 2,
                'source_name' => $trip->origin_port_name ?? 'Origin Port',
                'destination_name' => $trip->destination_port_name ?? 'Destination Port',
                'transport_mode' => 'sea',
                'tracking_source' => null,
                'notes' => 'Ocean freight leg.',
            ],
        ];
    }
}
