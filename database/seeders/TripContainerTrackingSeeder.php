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
                if (TripContainerTracking::where('trip_id', $trip->id)->exists()) continue;
                if ($trip->status === TripStatus::Draft) continue;

                $def = $this->definitionFor($trip);
                TripContainerTracking::create(array_merge([
                    'trip_id' => $trip->id,
                    'customer_id' => $trip->customer_id,
                    'container_number' => $trip->container_number,
                    'carrier_scac' => $trip->carrier_scac ?? 'MSCU',
                ], $def));
                $total++;
            }
        }

        $this->command->info("  TripContainerTrackingSeeder: {$total} records seeded.");
    }

    private function definitionFor(Trip $trip): array
    {
        $tid = $trip->id;
        $cid = $trip->customer_id;

        return match ($trip->status) {

            TripStatus::InTransit => [
                'mt_tracking_request_id' => 'KPLR-REQ-' . $cid . '-' . $tid,
                'mt_shipment_id' => null,
                'tracking_status' => 'pending',
                'transportation_status' => 'in_transit',
                'pol_name' => $trip->origin_port_name,
                'pol_unlocode' => $this->toUnlocode($trip->origin_port_code),
                'pod_name' => $trip->destination_port_name,
                'pod_unlocode' => $this->toUnlocode($trip->destination_port_code),
                'last_synced_at' => now()->subHours(2),
            ],

            TripStatus::AtPort => [
                'mt_tracking_request_id' => 'KPLR-REQ-' . $cid . '-' . $tid,
                'mt_shipment_id' => 'SHP-' . strtoupper(substr($trip->trip_ref, -4)) . '-' . $tid,
                'tracking_status' => 'active',
                'transportation_status' => 'at_port_of_loading',
                'pol_name' => $trip->origin_port_name,
                'pol_unlocode' => $this->toUnlocode($trip->origin_port_code),
                'pod_name' => $trip->destination_port_name,
                'pod_unlocode' => $this->toUnlocode($trip->destination_port_code),
                'arrival_delay_days' => 0,
                'initial_carrier_eta' => $trip->eta ?? now()->addDays(22),
                'has_rollover' => false,
                'current_vessel_name' => null,
                'last_synced_at' => now()->subMinutes(30),
            ],

            TripStatus::OnVessel => [
                'mt_tracking_request_id' => 'KPLR-REQ-' . $cid . '-' . $tid,
                'mt_shipment_id' => 'SHP-' . strtoupper(substr($trip->trip_ref, -4)) . '-' . $tid,
                'tracking_status' => 'active',
                'transportation_status' => 'in_transit',
                'pol_name' => $trip->origin_port_name,
                'pol_unlocode' => $this->toUnlocode($trip->origin_port_code),
                'pod_name' => $trip->destination_port_name,
                'pod_unlocode' => $this->toUnlocode($trip->destination_port_code),
                'arrival_delay_days' => 0,
                'initial_carrier_eta' => $trip->eta ?? now()->addDays(14),
                'has_rollover' => false,
                'current_vessel_name' => $trip->vessel_name,
                'current_vessel_imo' => $trip->vessel_imo_number,
                'current_vessel_lat' => $trip->last_known_lat,
                'current_vessel_lng' => $trip->last_known_lng,
                'current_vessel_speed' => round(rand(145, 215) / 10, 1),
                'current_vessel_heading' => rand(250, 360),
                'current_vessel_geo_area' => $this->geoArea($trip->trip_ref),
                'current_vessel_position_at' => now()->subHours(1),
                'last_synced_at' => now()->subHours(1),
            ],

            TripStatus::InTransshipment => [
                'mt_tracking_request_id' => 'KPLR-REQ-' . $cid . '-' . $tid,
                'mt_shipment_id' => 'SHP-' . strtoupper(substr($trip->trip_ref, -4)) . '-' . $tid,
                'tracking_status' => 'active',
                'transportation_status' => 'transshipment',
                'pol_name' => $trip->origin_port_name,
                'pol_unlocode' => $this->toUnlocode($trip->origin_port_code),
                'pod_name' => $trip->destination_port_name,
                'pod_unlocode' => $this->toUnlocode($trip->destination_port_code),
                'arrival_delay_days' => 2,
                'initial_carrier_eta' => $trip->eta ?? now()->addDays(8),
                'has_rollover' => false,
                'current_vessel_name' => $trip->vessel_name,
                'current_vessel_imo' => $trip->vessel_imo_number,
                'current_vessel_lat' => 6.9271,   // Colombo
                'current_vessel_lng' => 79.8612,
                'current_vessel_speed' => 0.5,
                'current_vessel_heading' => 0,
                'current_vessel_geo_area' => 'Colombo Transshipment Port',
                'current_vessel_position_at' => now()->subHours(3),
                'last_synced_at' => now()->subHours(3),
            ],

            TripStatus::VesselArrived => [
                'mt_tracking_request_id' => 'KPLR-REQ-' . $cid . '-' . $tid,
                'mt_shipment_id' => 'SHP-' . strtoupper(substr($trip->trip_ref, -4)) . '-' . $tid,
                'tracking_status' => 'active',
                'transportation_status' => 'arrived',
                'pol_name' => $trip->origin_port_name,
                'pol_unlocode' => $this->toUnlocode($trip->origin_port_code),
                'pod_name' => $trip->destination_port_name,
                'pod_unlocode' => $this->toUnlocode($trip->destination_port_code),
                'arrival_delay_days' => rand(0, 3),
                'initial_carrier_eta' => $trip->eta ?? now()->subDays(2),
                'has_rollover' => false,
                'current_vessel_name' => $trip->vessel_name,
                'current_vessel_imo' => $trip->vessel_imo_number,
                'current_vessel_lat' => $trip->delivery_lat ?? $trip->last_known_lat,
                'current_vessel_lng' => $trip->delivery_lng ?? $trip->last_known_lng,
                'current_vessel_speed' => 0.0,
                'current_vessel_heading' => 0,
                'current_vessel_geo_area' => 'Destination Port — Berth',
                'current_vessel_position_at' => now()->subHours(6),
                'last_synced_at' => now()->subHours(4),
            ],

            TripStatus::Delivered, TripStatus::Completed => [
                'mt_tracking_request_id' => 'KPLR-REQ-' . $cid . '-' . $tid,
                'mt_shipment_id' => 'SHP-' . strtoupper(substr($trip->trip_ref, -4)) . '-' . $tid,
                'tracking_status' => 'active',
                'transportation_status' => 'delivered',
                'pol_name' => $trip->origin_port_name,
                'pol_unlocode' => $this->toUnlocode($trip->origin_port_code),
                'pod_name' => $trip->destination_port_name,
                'pod_unlocode' => $this->toUnlocode($trip->destination_port_code),
                'arrival_delay_days' => 1,
                'initial_carrier_eta' => $trip->eta ?? now()->subDays(20),
                'has_rollover' => false,
                'current_vessel_name' => $trip->vessel_name,
                'current_vessel_imo' => $trip->vessel_imo_number,
                'last_synced_at' => now()->subDays(2),
            ],

            default => [
                'tracking_status' => 'not_registered',
            ],
        };
    }

    private function toUnlocode(?string $code): ?string
    {
        if (!$code) return null;

        return match ($code) {
            'INNSA' => 'INNSA',
            'INMUN' => 'INMUN',
            'INMAA' => 'INMAA',
            'INVTZ' => 'INVTZ',
            'INCOK' => 'INCOK',
            'AEJEA' => 'AEJEA',
            'NLRTM' => 'NLRTM',
            'SGSIN' => 'SGSIN',
            'CNSHA' => 'CNSHA',
            'DEHAM' => 'DEHAM',
            default => strtoupper($code),
        };
    }

    private function geoArea(string $tripRef): string
    {
        return match (true) {
            str_contains($tripRef, 'T05') => 'Red Sea (off Jeddah)',
            str_contains($tripRef, 'T06') => 'Colombo Transshipment',
            str_contains($tripRef, 'T02') => 'Bay of Bengal',
            default => 'Arabian Sea',
        };
    }
}
