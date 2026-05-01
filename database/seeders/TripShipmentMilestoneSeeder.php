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
            if (TripShipmentMilestone::where('trip_id', $record->trip_id)->exists()) continue;

            foreach ($this->milestonesFor($record) as $milestone) {
                TripShipmentMilestone::insert([
                    'trip_id' => $record->trip_id,
                    'customer_id' => $record->customer_id,
                    'mt_event_id' => $milestone['mt_event_id'],
                    'event_type' => $milestone['event_type'],
                    'event_classifier' => $milestone['event_classifier'],
                    'location_name' => $milestone['location_name'],
                    'location_unlocode' => $milestone['location_unlocode'],
                    'location_country' => $milestone['location_country'],
                    'location_lat' => $milestone['lat'] ?? null,
                    'location_lng' => $milestone['lng'] ?? null,
                    'terminal_name' => $milestone['terminal_name'] ?? null,
                    'vessel_name' => $record->trip->vessel_name,
                    'vessel_imo' => $record->trip->vessel_imo_number,
                    'voyage_number' => $record->trip->voyage_number,
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
        $ref = $trip->trip_ref;

        // Determine route scenario
        $isExportToRotterdam = str_contains($ref, 'T05');
        $isExportToJebelAli = in_array($ref, ['TR_VL_T01', 'TR_VL_T04']);
        $isToHamburg = in_array($ref, ['TR_II_T01', 'TR_II_T03']);
        $isImportFromJebel = in_array($ref, ['TR_VL_T02', 'TR_VL_T07']);
        $isFromSingapore = in_array($ref, ['TR_VL_T09', 'TR_II_T05', 'TR_II_T06']);
        $isFromShanghai = $ref === 'TR_II_T02';
        $isTransshipColombo = $ref === 'TR_VL_T06';

        if ($isExportToJebelAli) {
            return $this->jnptToJebelAliMilestones($tid, $trip);
        }

        if ($isExportToRotterdam) {
            return $this->jnptToRotterdamMilestones($tid, $trip);
        }

        if ($isToHamburg) {
            return $this->chennaiToHamburgMilestones($tid, $trip);
        }

        if ($isImportFromJebel) {
            return $this->jebelAliToIndiaMilestones($tid, $trip);
        }

        if ($isFromSingapore) {
            return $this->singaporeToIndiaMilestones($tid, $trip);
        }

        if ($isFromShanghai) {
            return $this->shanghaiToChennaiMilestones($tid, $trip);
        }

        if ($isTransshipColombo) {
            return $this->jnptToSingaporeViaColomboMilestones($tid, $trip);
        }

        return $this->genericMilestones($tid, $trip);
    }

    private function jnptToJebelAliMilestones(int $tid, $trip): array
    {
        return [
            $this->m($tid, 1, 'gate_in', 'actual', 'JNPT CFS Gateway Terminal', 'INNSA', 'India', 18.9450, 72.9600, 'Gateway Terminals India', 'port_of_loading', now()->subDays(38)),
            $this->m($tid, 2, 'load', 'actual', 'Jawaharlal Nehru Port (JNPT)', 'INNSA', 'India', 18.9488, 72.9511, 'GTI Berth 8', 'port_of_loading', now()->subDays(37)),
            $this->m($tid, 3, 'departure', 'actual', 'Jawaharlal Nehru Port (JNPT)', 'INNSA', 'India', 18.9488, 72.9511, null, 'port_of_loading', now()->subDays(37)),
            $this->m($tid, 4, 'arrival', 'actual', 'Jebel Ali Port', 'AEJEA', 'UAE', 24.9857, 55.0272, 'Jebel Ali Terminal 3', 'port_of_discharge', now()->subDays(30)),
            $this->m($tid, 5, 'unload', 'actual', 'Jebel Ali Port', 'AEJEA', 'UAE', 24.9857, 55.0272, 'Jebel Ali Terminal 3', 'port_of_discharge', now()->subDays(30)),
            $this->m($tid, 6, 'gate_out', 'actual', 'Jebel Ali Port', 'AEJEA', 'UAE', 24.9857, 55.0272, null, 'port_of_discharge', now()->subDays(29)),
            $this->m($tid, 7, 'delivery', 'actual', 'Al Quoz Industrial Area Warehouse', 'AEJEA', 'UAE', 25.1472, 55.2180, null, 'final_delivery', now()->subDays(28)),
        ];
    }

    private function jnptToRotterdamMilestones(int $tid, $trip): array
    {
        return [
            $this->m($tid, 1, 'gate_in', 'actual', 'JNPT CFS Gateway Terminal', 'INNSA', 'India', 18.9450, 72.9600, 'GTI Berth 7', 'port_of_loading', now()->subDays(14)),
            $this->m($tid, 2, 'load', 'actual', 'Jawaharlal Nehru Port (JNPT)', 'INNSA', 'India', 18.9488, 72.9511, 'GTI Berth 7', 'port_of_loading', now()->subDays(13)),
            $this->m($tid, 3, 'departure', 'actual', 'Jawaharlal Nehru Port (JNPT)', 'INNSA', 'India', 18.9488, 72.9511, null, 'port_of_loading', now()->subDays(13)),
            $this->m($tid, 4, 'arrival', 'planned', 'Suez Canal (Port Said)', 'EGPSD', 'Egypt', 31.2565, 32.2841, 'Suez Canal Authority', 'transshipment', now()->addDays(4)),
            $this->m($tid, 5, 'departure', 'planned', 'Suez Canal (Port Said)', 'EGPSD', 'Egypt', 31.2565, 32.2841, null, 'transshipment', now()->addDays(5)),
            $this->m($tid, 6, 'arrival', 'planned', 'Port of Rotterdam (ECT Delta)', 'NLRTM', 'Netherlands', 51.9244, 4.4777, 'ECT Delta Terminal', 'port_of_discharge', now()->addDays(14)),
            $this->m($tid, 7, 'unload', 'planned', 'Port of Rotterdam (ECT Delta)', 'NLRTM', 'Netherlands', 51.9244, 4.4777, 'ECT Delta Terminal', 'port_of_discharge', now()->addDays(15)),
        ];
    }

    private function chennaiToHamburgMilestones(int $tid, $trip): array
    {
        $isCompleted = $trip->status->value === 'completed';

        return [
            $this->m($tid, 1, 'gate_in', 'actual', 'Chennai Port CFS (CCTL)', 'INMAA', 'India', 13.0800, 80.2850, 'CCTL Terminal', 'port_of_loading', now()->subDays(58)),
            $this->m($tid, 2, 'load', 'actual', 'Chennai Port (Kamarajar)', 'INMAA', 'India', 13.0836, 80.2969, 'Bharati Dock', 'port_of_loading', now()->subDays(57)),
            $this->m($tid, 3, 'departure', 'actual', 'Chennai Port (Kamarajar)', 'INMAA', 'India', 13.0836, 80.2969, null, 'port_of_loading', now()->subDays(57)),
            $this->m($tid, 4, 'arrival', 'actual', 'Colombo Port (SLPA)', 'LKCMB', 'Sri Lanka', 6.9271, 79.8612, 'Jaye Container Terminal', 'transshipment', now()->subDays(55)),
            $this->m($tid, 5, 'unload', 'actual', 'Colombo Port (SLPA)', 'LKCMB', 'Sri Lanka', 6.9271, 79.8612, 'JCT Berth 4', 'transshipment', now()->subDays(54)),
            $this->m($tid, 6, 'load', 'actual', 'Colombo Port (SLPA)', 'LKCMB', 'Sri Lanka', 6.9271, 79.8612, 'JCT Berth 4', 'transshipment', now()->subDays(53)),
            $this->m($tid, 7, 'departure', 'actual', 'Colombo Port (SLPA)', 'LKCMB', 'Sri Lanka', 6.9271, 79.8612, null, 'transshipment', now()->subDays(52)),
            $this->m($tid, 8, 'arrival', 'actual', 'Suez Canal (Port Said)', 'EGPSD', 'Egypt', 31.2565, 32.2841, 'Suez Canal Authority', 'transshipment', now()->subDays(42)),
            $this->m($tid, 9, 'departure', 'actual', 'Suez Canal (Port Said)', 'EGPSD', 'Egypt', 31.2565, 32.2841, null, 'transshipment', now()->subDays(41)),
            $this->m($tid, 10, 'arrival', $isCompleted ? 'actual' : 'planned',
                'Port of Hamburg (HHLA)', 'DEHAM', 'Germany', 53.5439, 9.9569, 'HHLA CTA Terminal', 'port_of_discharge', now()->subDays(28)),
            $this->m($tid, 11, 'unload', $isCompleted ? 'actual' : 'planned',
                'Port of Hamburg (HHLA)', 'DEHAM', 'Germany', 53.5439, 9.9569, 'HHLA CTA Terminal', 'port_of_discharge', now()->subDays(27)),
            $this->m($tid, 12, 'gate_out', $isCompleted ? 'actual' : 'planned',
                'Port of Hamburg (HHLA)', 'DEHAM', 'Germany', 53.5439, 9.9569, null, 'port_of_discharge', now()->subDays(26)),
        ];
    }

    private function jebelAliToIndiaMilestones(int $tid, $trip): array
    {
        $destPort = $trip->destination_port_code === 'INMUN' ? 'Mundra Port (APSEZ)' : 'Jawaharlal Nehru Port (JNPT)';
        $destUnlocode = $trip->destination_port_code ?? 'INNSA';
        $destLat = $trip->destination_port_code === 'INMUN' ? 22.8381 : 18.9488;
        $destLng = $trip->destination_port_code === 'INMUN' ? 69.7032 : 72.9511;
        $isArrived = in_array($trip->status->value, ['vessel_arrived', 'out_for_delivery', 'delivered', 'completed']);

        return [
            $this->m($tid, 1, 'gate_in', 'actual', 'Jebel Ali Port (JAFZA)', 'AEJEA', 'UAE', 24.9857, 55.0272, 'Terminal 1', 'port_of_loading', now()->subDays(20)),
            $this->m($tid, 2, 'load', 'actual', 'Jebel Ali Port (JAFZA)', 'AEJEA', 'UAE', 24.9857, 55.0272, 'Terminal 1 Berth', 'port_of_loading', now()->subDays(19)),
            $this->m($tid, 3, 'departure', 'actual', 'Jebel Ali Port (JAFZA)', 'AEJEA', 'UAE', 24.9857, 55.0272, null, 'port_of_loading', now()->subDays(19)),
            $this->m($tid, 4, 'arrival', $isArrived ? 'actual' : 'planned', $destPort, $destUnlocode, 'India', $destLat, $destLng, null, 'port_of_discharge', now()->subDays(14)),
            $this->m($tid, 5, 'unload', $isArrived ? 'actual' : 'planned', $destPort, $destUnlocode, 'India', $destLat, $destLng, null, 'port_of_discharge', now()->subDays(13)),
        ];
    }

    private function singaporeToIndiaMilestones(int $tid, $trip): array
    {
        $destPort = $trip->destination_port_code === 'INMAA' ? 'Chennai Port (Kamarajar)' : 'Jawaharlal Nehru Port (JNPT)';
        $destUnlocode = $trip->destination_port_code ?? 'INMAA';
        $destLat = $trip->destination_port_code === 'INMAA' ? 13.0836 : 18.9488;
        $destLng = $trip->destination_port_code === 'INMAA' ? 80.2969 : 72.9511;
        $isArrived = in_array($trip->status->value, ['at_port', 'vessel_arrived', 'out_for_delivery', 'delivered', 'completed']);

        return [
            $this->m($tid, 1, 'gate_in', 'actual', 'Singapore PSA (Tanjong Pagar)', 'SGSIN', 'Singapore', 1.2566, 103.8198, 'Tanjong Pagar Terminal', 'port_of_loading', now()->subDays(10)),
            $this->m($tid, 2, 'load', 'actual', 'Singapore PSA (Tanjong Pagar)', 'SGSIN', 'Singapore', 1.2566, 103.8198, 'TPT Berth 12', 'port_of_loading', now()->subDays(9)),
            $this->m($tid, 3, 'departure', 'actual', 'Singapore PSA (Tanjong Pagar)', 'SGSIN', 'Singapore', 1.2566, 103.8198, null, 'port_of_loading', now()->subDays(9)),
            $this->m($tid, 4, 'arrival', $isArrived ? 'actual' : 'planned', $destPort, $destUnlocode, 'India', $destLat, $destLng, null, 'port_of_discharge', now()->subDays($isArrived ? 2 : -3)),
            $this->m($tid, 5, 'unload', $isArrived ? 'actual' : 'planned', $destPort, $destUnlocode, 'India', $destLat, $destLng, null, 'port_of_discharge', now()->subDays($isArrived ? 1 : -4)),
        ];
    }

    private function shanghaiToChennaiMilestones(int $tid, $trip): array
    {
        return [
            $this->m($tid, 1, 'gate_in', 'actual', 'Yangshan Deep Water Port', 'CNSHA', 'China', 30.6218, 122.0580, 'Yangshan Terminal 4', 'port_of_loading', now()->subDays(18)),
            $this->m($tid, 2, 'load', 'actual', 'Yangshan Deep Water Port', 'CNSHA', 'China', 30.6218, 122.0580, 'Yangshan T4 Berth 8', 'port_of_loading', now()->subDays(17)),
            $this->m($tid, 3, 'departure', 'actual', 'Yangshan Deep Water Port', 'CNSHA', 'China', 30.6218, 122.0580, null, 'port_of_loading', now()->subDays(17)),
            $this->m($tid, 4, 'arrival', 'planned', 'Singapore PSA (transshipment)', 'SGSIN', 'Singapore', 1.2566, 103.8198, null, 'transshipment', now()->addDays(1)),
            $this->m($tid, 5, 'departure', 'planned', 'Singapore PSA (transshipment)', 'SGSIN', 'Singapore', 1.2566, 103.8198, null, 'transshipment', now()->addDays(2)),
            $this->m($tid, 6, 'arrival', 'planned', 'Chennai Port (Kamarajar)', 'INMAA', 'India', 13.0836, 80.2969, 'Bharati Dock', 'port_of_discharge', now()->addDays(4)),
            $this->m($tid, 7, 'unload', 'planned', 'Chennai Port (Kamarajar)', 'INMAA', 'India', 13.0836, 80.2969, 'Bharati Dock', 'port_of_discharge', now()->addDays(5)),
        ];
    }

    private function jnptToSingaporeViaColomboMilestones(int $tid, $trip): array
    {
        return [
            $this->m($tid, 1, 'gate_in', 'actual', 'JNPT CFS', 'INNSA', 'India', 18.9450, 72.9600, 'GTI', 'port_of_loading', now()->subDays(22)),
            $this->m($tid, 2, 'load', 'actual', 'Jawaharlal Nehru Port (JNPT)', 'INNSA', 'India', 18.9488, 72.9511, 'GTI Berth 6', 'port_of_loading', now()->subDays(21)),
            $this->m($tid, 3, 'departure', 'actual', 'Jawaharlal Nehru Port (JNPT)', 'INNSA', 'India', 18.9488, 72.9511, null, 'port_of_loading', now()->subDays(21)),
            $this->m($tid, 4, 'arrival', 'actual', 'Colombo Port (transshipment)', 'LKCMB', 'Sri Lanka', 6.9271, 79.8612, 'JCT Terminal', 'transshipment', now()->subDays(18)),
            $this->m($tid, 5, 'unload', 'actual', 'Colombo Port (transshipment)', 'LKCMB', 'Sri Lanka', 6.9271, 79.8612, 'JCT Berth 3', 'transshipment', now()->subDays(17)),
            $this->m($tid, 6, 'load', 'actual', 'Colombo Port (transshipment)', 'LKCMB', 'Sri Lanka', 6.9271, 79.8612, 'JCT Berth 3', 'transshipment', now()->subDays(16)),
            $this->m($tid, 7, 'departure', 'actual', 'Colombo Port (transshipment)', 'LKCMB', 'Sri Lanka', 6.9271, 79.8612, null, 'transshipment', now()->subDays(15)),
            $this->m($tid, 8, 'arrival', 'planned', 'Port of Singapore (PSA)', 'SGSIN', 'Singapore', 1.2566, 103.8198, 'Brani Terminal', 'port_of_discharge', now()->addDays(8)),
            $this->m($tid, 9, 'unload', 'planned', 'Port of Singapore (PSA)', 'SGSIN', 'Singapore', 1.2566, 103.8198, 'Brani Terminal', 'port_of_discharge', now()->addDays(9)),
        ];
    }

    private function genericMilestones(int $tid, $trip): array
    {
        $polName = $trip->origin_port_name ?? 'Origin Port';
        $polUnlocode = $trip->origin_port_code ?? 'INNSA';
        $podName = $trip->destination_port_name ?? 'Destination Port';
        $podUnlocode = $trip->destination_port_code ?? 'UNKWN';

        return [
            $this->m($tid, 1, 'load', 'actual', $polName, $polUnlocode, 'India', null, null, null, 'port_of_loading', now()->subDays(20)),
            $this->m($tid, 2, 'departure', 'actual', $polName, $polUnlocode, 'India', null, null, null, 'port_of_loading', now()->subDays(19)),
            $this->m($tid, 3, 'arrival', 'planned', $podName, $podUnlocode, null, null, null, null, 'port_of_discharge', now()->addDays(10)),
            $this->m($tid, 4, 'unload', 'planned', $podName, $podUnlocode, null, null, null, null, 'port_of_discharge', now()->addDays(11)),
        ];
    }

    /** Milestone row builder helper */
    private function m(
        int    $tripId, int $seq, string $eventType, string $classifier,
        string $locName, string $locUnlocode, ?string $country,
        ?float $lat, ?float $lng, ?string $terminal,
        string $locationType, $occurredAt
    ): array
    {
        return [
            'mt_event_id' => "EVT-{$tripId}-" . str_pad($seq, 3, '0', STR_PAD_LEFT),
            'event_type' => $eventType,
            'event_classifier' => $classifier,
            'location_name' => $locName,
            'location_unlocode' => $locUnlocode,
            'location_country' => $country,
            'lat' => $lat,
            'lng' => $lng,
            'terminal_name' => $terminal,
            'location_type' => $locationType,
            'sequence' => $seq,
            'occurred_at' => $occurredAt,
        ];
    }
}
