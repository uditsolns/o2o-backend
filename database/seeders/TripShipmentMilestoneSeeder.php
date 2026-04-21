<?php

namespace Database\Seeders;

use App\Models\TripContainerTracking;
use App\Models\TripShipmentMilestone;
use Illuminate\Database\Seeder;

class TripShipmentMilestoneSeeder extends Seeder
{
    public function run(): void
    {
        $records = TripContainerTracking::whereNotNull('mt_shipment_id')
            ->where('tracking_status', 'active')
            ->with('trip')
            ->get();

        $total = 0;

        foreach ($records as $record) {
            if (TripShipmentMilestone::where('trip_id', $record->trip_id)->exists()) {
                continue;
            }

            $trip = $record->trip;
            $milestones = $this->milestonesFor($record);

            foreach ($milestones as $milestone) {
                TripShipmentMilestone::insert([
                    'trip_id' => $record->trip_id,
                    'customer_id' => $record->customer_id,
                    'mt_event_id' => $milestone['mt_event_id'],
                    'event_type' => $milestone['event_type'],
                    'event_classifier' => $milestone['event_classifier'],
                    'location_name' => $milestone['location_name'],
                    'location_unlocode' => $milestone['location_unlocode'],
                    'location_country' => 'India',
                    'location_lat' => $milestone['lat'] ?? null,
                    'location_lng' => $milestone['lng'] ?? null,
                    'terminal_name' => $milestone['terminal_name'] ?? null,
                    'vessel_name' => $trip->vessel_name,
                    'vessel_imo' => $trip->vessel_imo_number,
                    'voyage_number' => $trip->voyage_number,
                    'location_type' => $milestone['location_type'],
                    'sequence_order' => $milestone['sequence'],
                    'occurred_at' => $milestone['occurred_at'],
                    'created_at' => now(),
                ]);
                $total++;
            }
        }

        $this->command->info("  TripShipmentMilestoneSeeder: {$total} milestones seeded.");
    }

    private function milestonesFor(TripContainerTracking $record): array
    {
        $trip = $record->trip;
        $tid = $record->trip_id;
        $pol = $record->pol_name ?? 'JNPT';
        $pod = $record->pod_name ?? 'Destination Port';
        $polCode = $record->pol_unlocode ?? 'INNSA';
        $podCode = $record->pod_unlocode ?? 'XXPOD';

        return [
            [
                'mt_event_id' => "EVT-{$tid}-001",
                'event_type' => 'load',
                'event_classifier' => 'actual',
                'location_name' => $pol,
                'location_unlocode' => $polCode,
                'terminal_name' => 'Gateway Terminal',
                'location_type' => 'port_of_loading',
                'sequence' => 1,
                'occurred_at' => now()->subDays(22),
                'lat' => 18.9388,
                'lng' => 72.9561,
            ],
            [
                'mt_event_id' => "EVT-{$tid}-002",
                'event_type' => 'departure',
                'event_classifier' => 'actual',
                'location_name' => $pol,
                'location_unlocode' => $polCode,
                'terminal_name' => null,
                'location_type' => 'port_of_loading',
                'sequence' => 2,
                'occurred_at' => now()->subDays(21),
                'lat' => 18.9388,
                'lng' => 72.9561,
            ],
            [
                'mt_event_id' => "EVT-{$tid}-003",
                'event_type' => 'arrival',
                'event_classifier' => 'planned',
                'location_name' => $pod,
                'location_unlocode' => $podCode,
                'terminal_name' => 'Main Terminal',
                'location_type' => 'port_of_discharge',
                'sequence' => 3,
                'occurred_at' => $trip->eta ?? now()->addDays(10),
                'lat' => null,
                'lng' => null,
            ],
            [
                'mt_event_id' => "EVT-{$tid}-004",
                'event_type' => 'unload',
                'event_classifier' => 'planned',
                'location_name' => $pod,
                'location_unlocode' => $podCode,
                'terminal_name' => 'Main Terminal',
                'location_type' => 'port_of_discharge',
                'sequence' => 4,
                'occurred_at' => $trip->eta ? now()->addDays(1) : now()->addDays(11),
                'lat' => null,
                'lng' => null,
            ],
        ];
    }
}
