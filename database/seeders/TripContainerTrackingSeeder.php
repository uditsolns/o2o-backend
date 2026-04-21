<?php

namespace Database\Seeders;

use App\Enums\CustomerOnboardingStatus;
use App\Enums\TripStatus;
use App\Enums\TripTransportationMode;
use App\Models\Customer;
use App\Models\Trip;
use App\Models\TripContainerTracking;
use Illuminate\Database\Seeder;

class TripContainerTrackingSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::where('onboarding_status', CustomerOnboardingStatus::Completed->value)->get();
        $total = 0;

        foreach ($customers as $customer) {
            $trips = Trip::where('customer_id', $customer->id)
                ->whereIn('transport_mode', [
                    TripTransportationMode::Sea->value,
                    TripTransportationMode::Multimodal->value,
                ])
                ->whereNotNull('container_number')
                ->get();

            foreach ($trips as $trip) {
                if (TripContainerTracking::where('trip_id', $trip->id)->exists()) {
                    continue;
                }

                // Draft trips are not yet registered
                if ($trip->status === TripStatus::Draft) {
                    continue;
                }

                $def = $this->definitionFor($trip);

                TripContainerTracking::create([
                    'trip_id' => $trip->id,
                    'customer_id' => $trip->customer_id,
                    'container_number' => $trip->container_number,
                    'carrier_scac' => $trip->carrier_scac ?? 'MSCU',
                    ...$def,
                ]);
                $total++;
            }
        }

        $this->command->info("  TripContainerTrackingSeeder: {$total} records seeded.");
    }

    private function definitionFor(Trip $trip): array
    {
        $cid = $trip->customer_id;
        $tid = $trip->id;

        return match ($trip->status) {
            TripStatus::InTransit => [
                'mt_tracking_request_id' => 'KPLR-REQ-' . $cid . '-' . $tid,
                'mt_shipment_id' => null,
                'tracking_status' => 'pending',
                'failed_reason' => null,
                'transportation_status' => 'in_transit',
                'pol_name' => $trip->origin_port_name,
                'pol_unlocode' => $this->portCodeToUnlocode($trip->origin_port_code),
                'pod_name' => $trip->destination_port_name,
                'pod_unlocode' => $this->portCodeToUnlocode($trip->destination_port_code),
                'last_synced_at' => now()->subHours(2),
            ],
            TripStatus::AtPort => [
                'mt_tracking_request_id' => 'KPLR-REQ-' . $cid . '-' . $tid,
                'mt_shipment_id' => 'SHP-' . $cid . '-' . $tid,
                'tracking_status' => 'active',
                'transportation_status' => 'at_port',
                'pol_name' => $trip->origin_port_name,
                'pol_unlocode' => $this->portCodeToUnlocode($trip->origin_port_code),
                'pod_name' => $trip->destination_port_name,
                'pod_unlocode' => $this->portCodeToUnlocode($trip->destination_port_code),
                'arrival_delay_days' => 0,
                'initial_carrier_eta' => now()->addDays(18),
                'has_rollover' => false,
                'last_synced_at' => now()->subMinutes(30),
            ],
            TripStatus::OnVessel, TripStatus::InTransshipment => [
                'mt_tracking_request_id' => 'KPLR-REQ-' . $cid . '-' . $tid,
                'mt_shipment_id' => 'SHP-' . $cid . '-' . $tid,
                'tracking_status' => 'active',
                'transportation_status' => 'on_vessel',
                'pol_name' => $trip->origin_port_name,
                'pol_unlocode' => $this->portCodeToUnlocode($trip->origin_port_code),
                'pod_name' => $trip->destination_port_name,
                'pod_unlocode' => $this->portCodeToUnlocode($trip->destination_port_code),
                'arrival_delay_days' => rand(0, 3),
                'initial_carrier_eta' => $trip->eta ?? now()->addDays(15),
                'has_rollover' => false,
                'current_vessel_name' => $trip->vessel_name,
                'current_vessel_imo' => $trip->vessel_imo_number,
                'current_vessel_lat' => fake()->latitude(5, 25),
                'current_vessel_lng' => fake()->longitude(55, 80),
                'current_vessel_speed' => round(fake()->randomFloat(1, 14.0, 22.0), 1),
                'current_vessel_heading' => rand(180, 359),
                'current_vessel_geo_area' => 'Arabian Sea',
                'current_vessel_position_at' => now()->subHours(1),
                'last_synced_at' => now()->subHours(1),
            ],
            TripStatus::VesselArrived, TripStatus::OutForDelivery, TripStatus::Delivered => [
                'mt_tracking_request_id' => 'KPLR-REQ-' . $cid . '-' . $tid,
                'mt_shipment_id' => 'SHP-' . $cid . '-' . $tid,
                'tracking_status' => 'active',
                'transportation_status' => 'arrived',
                'pol_name' => $trip->origin_port_name,
                'pol_unlocode' => $this->portCodeToUnlocode($trip->origin_port_code),
                'pod_name' => $trip->destination_port_name,
                'pod_unlocode' => $this->portCodeToUnlocode($trip->destination_port_code),
                'arrival_delay_days' => rand(0, 5),
                'initial_carrier_eta' => $trip->eta ?? now()->subDays(5),
                'has_rollover' => false,
                'current_vessel_name' => $trip->vessel_name,
                'current_vessel_imo' => $trip->vessel_imo_number,
                'last_synced_at' => now()->subHours(6),
            ],
            TripStatus::Completed => [
                'mt_tracking_request_id' => 'KPLR-REQ-' . $cid . '-' . $tid,
                'mt_shipment_id' => 'SHP-' . $cid . '-' . $tid,
                'tracking_status' => 'active',
                'transportation_status' => 'delivered',
                'pol_name' => $trip->origin_port_name,
                'pol_unlocode' => $this->portCodeToUnlocode($trip->origin_port_code),
                'pod_name' => $trip->destination_port_name,
                'pod_unlocode' => $this->portCodeToUnlocode($trip->destination_port_code),
                'arrival_delay_days' => 2,
                'initial_carrier_eta' => $trip->eta ?? now()->subDays(25),
                'has_rollover' => false,
                'last_synced_at' => now()->subDays(1),
            ],
            default => [
                'mt_tracking_request_id' => null,
                'mt_shipment_id' => null,
                'tracking_status' => 'not_registered',
            ],
        };
    }

    private function portCodeToUnlocode(?string $code): ?string
    {
        if (!$code) return null;
        return match ($code) {
            'INNSA1' => 'INNSA',
            'INMUN1' => 'INMUN',
            'INMAA1' => 'INMAA',
            'INVTZ1' => 'INVTZ',
            default => 'IN' . substr($code, 2, 3),
        };
    }
}
