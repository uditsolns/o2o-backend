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
            foreach (Trip::where('customer_id', $customer->id)->get() as $trip) {
                if (TripSegment::where('trip_id', $trip->id)->exists()) continue;

                foreach ($this->segmentsFor($trip) as $seq => $seg) {
                    TripSegment::create(array_merge($seg, [
                        'trip_id' => $trip->id,
                        'customer_id' => $trip->customer_id,
                        'sequence' => $seq + 1,
                    ]));
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
        return [[
            'source_name' => $trip->dispatch_location_name ?? ($trip->dispatch_city . ', ' . $trip->dispatch_state),
            'destination_name' => $trip->delivery_location_name ?? ($trip->delivery_city . ', ' . $trip->delivery_state),
            'transport_mode' => 'road',
            'tracking_source' => 'driver_mobile',
            'notes' => 'Road leg via National Highway. FastTag active.',
        ]];
    }

    private function seaSegments(Trip $trip): array
    {
        return [[
            'source_name' => ($trip->origin_port_name ?? 'Origin Port') . ' (' . ($trip->origin_port_code ?? '?') . ')',
            'destination_name' => ($trip->destination_port_name ?? 'Destination Port') . ' (' . ($trip->destination_port_code ?? '?') . ')',
            'transport_mode' => 'sea',
            'tracking_source' => 'vessel_ais',
            'notes' => 'Ocean freight leg — AIS vessel tracking active.',
        ]];
    }

    private function multimodalSegments(Trip $trip): array
    {
        // Export: dispatch → origin port (road), then origin port → dest port (sea)
        // Import: origin port (foreign) → dest port (sea), then dest port → delivery (road)
        if ($trip->trip_type?->value === 'export') {
            return [
                [
                    'source_name' => $trip->dispatch_location_name ?? $trip->dispatch_city,
                    'destination_name' => ($trip->origin_port_name ?? 'Origin Port') . ' (' . $trip->origin_port_code . ')',
                    'transport_mode' => 'road',
                    'tracking_source' => 'driver_mobile',
                    'notes' => 'Road leg: factory to port of loading. FastTag + mobile tracking.',
                ],
                [
                    'source_name' => ($trip->origin_port_name ?? 'Origin Port') . ' (' . $trip->origin_port_code . ')',
                    'destination_name' => ($trip->destination_port_name ?? 'Destination Port') . ' (' . $trip->destination_port_code . ')',
                    'transport_mode' => 'sea',
                    'tracking_source' => 'vessel_ais',
                    'notes' => 'Ocean freight leg — AIS tracking.',
                ],
            ];
        }

        // Import
        return [
            [
                'source_name' => ($trip->origin_port_name ?? 'Origin Port') . ' (' . $trip->origin_port_code . ')',
                'destination_name' => ($trip->destination_port_name ?? 'Dest Port') . ' (' . $trip->destination_port_code . ')',
                'transport_mode' => 'sea',
                'tracking_source' => 'vessel_ais',
                'notes' => 'Ocean freight leg — AIS tracking.',
            ],
            [
                'source_name' => ($trip->destination_port_name ?? 'Dest Port') . ' (' . $trip->destination_port_code . ')',
                'destination_name' => $trip->delivery_location_name ?? $trip->delivery_city,
                'transport_mode' => 'road',
                'tracking_source' => 'driver_mobile',
                'notes' => 'Road leg: port to delivery location. FastTag + mobile tracking.',
            ],
        ];
    }
}
