<?php

namespace Database\Seeders;

use App\Models\TripContainerTracking;
use App\Models\TripShipmentMilestone;
use Illuminate\Database\Seeder;

/**
 * Seeds trip_shipment_milestones for each tracked (active) container shipment.
 *
 * Vessel data is now read from trip_container_tracking (current_vessel JSON snapshot)
 * — the trips table no longer carries flat vessel_name / vessel_imo_number / voyage_number.
 *
 * New columns populated (per migration 2026_05_18_000002):
 *   - event_category        (transport | equipment)
 *   - event_classifier      (actual | planned)
 *   - vessel_mmsi
 *   - voyage_number
 *   - mode_of_transport
 *   - local_time_offset
 *   - equipment_indicator
 *   - vessel_operational_status
 *   - sequence_order (int)
 */
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

            $vessel = $record->current_vessel ?? [];
            $vesselName = $vessel['name'] ?? null;
            $vesselImo = $vessel['imo'] ?? $record->current_vessel_imo ?? null;
            $vesselMmsi = $vessel['mmsi'] ?? $this->mmsiFromImo($vesselImo);

            foreach ($this->milestonesFor($record, $vesselName, $vesselImo, $vesselMmsi) as $milestone) {
                TripShipmentMilestone::insert([
                    'trip_id' => $record->trip_id,
                    'customer_id' => $record->customer_id,
                    'mt_event_id' => $milestone['mt_event_id'],
                    'event_type' => $milestone['event_type'],
                    'event_category' => $milestone['event_category'],
                    'event_classifier' => $milestone['event_classifier'],
                    'location_name' => $milestone['location_name'],
                    'location_unlocode' => $milestone['location_unlocode'],
                    'location_country' => $milestone['location_country'],
                    'location_lat' => $milestone['lat'] ?? null,
                    'location_lng' => $milestone['lng'] ?? null,
                    'terminal_name' => $milestone['terminal_name'] ?? null,
                    'vessel_name' => $vesselName,
                    'vessel_imo' => $vesselImo,
                    'vessel_mmsi' => $vesselMmsi,
                    'voyage_number' => $record->trip?->voyage_number ?? $milestone['voyage_number'] ?? null,
                    'vessel_operational_status' => $milestone['vessel_operational_status'] ?? 'underway',
                    'mode_of_transport' => $milestone['mode_of_transport'] ?? 'sea',
                    'local_time_offset' => $milestone['local_time_offset'] ?? null,
                    'equipment_indicator' => $milestone['equipment_indicator'] ?? 'laden',
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

    // ── Route dispatch ────────────────────────────────────────────────────────

    private function milestonesFor(TripContainerTracking $record, ?string $vesselName, ?string $vesselImo, ?string $vesselMmsi): array
    {
        $trip = $record->trip;
        $tid = $record->trip_id;
        $ref = $trip?->trip_ref ?? '';

        $isExportToRotterdam = str_contains($ref, 'T05');
        $isExportToJebelAli = in_array($ref, ['TR_VL_T01', 'TR_VL_T04'], true);
        $isToHamburg = in_array($ref, ['TR_II_T01', 'TR_II_T03'], true);
        $isImportFromJebel = in_array($ref, ['TR_VL_T02', 'TR_VL_T07'], true);
        $isFromSingapore = in_array($ref, ['TR_VL_T09', 'TR_II_T05', 'TR_II_T06'], true);
        $isFromShanghai = $ref === 'TR_II_T02';
        $isTransshipColombo = $ref === 'TR_VL_T06';

        return match (true) {
            $isExportToJebelAli => $this->jnptToJebelAliMilestones($tid, $trip),
            $isExportToRotterdam => $this->jnptToRotterdamMilestones($tid, $trip),
            $isToHamburg => $this->chennaiToHamburgMilestones($tid, $trip),
            $isImportFromJebel => $this->jebelAliToIndiaMilestones($tid, $trip),
            $isFromSingapore => $this->singaporeToIndiaMilestones($tid, $trip),
            $isFromShanghai => $this->shanghaiToChennaiMilestones($tid, $trip),
            $isTransshipColombo => $this->jnptToSingaporeViaColomboMilestones($tid, $trip),
            default => $this->genericMilestones($tid, $trip),
        };
    }

    // ── Per-route milestone definitions ────────────────────────────────────────

    private function jnptToJebelAliMilestones(int $tid, $trip): array
    {
        return [
            $this->m($tid, 1, 'gate_in', 'transport', 'actual', 'JNPT CFS Gateway Terminal', 'INNSA', 'India', 18.9450, 72.9600, 'Gateway Terminals India', 'port_of_loading', '5.5', now()->subDays(38)),
            $this->m($tid, 2, 'load', 'transport', 'actual', 'Jawaharlal Nehru Port (JNPT)', 'INNSA', 'India', 18.9488, 72.9511, 'GTI Berth 8', 'port_of_loading', '5.5', now()->subDays(37)),
            $this->m($tid, 3, 'departure', 'transport', 'actual', 'Jawaharlal Nehru Port (JNPT)', 'INNSA', 'India', 18.9488, 72.9511, null, 'port_of_loading', '5.5', now()->subDays(37)),
            $this->m($tid, 4, 'arrival', 'transport', 'actual', 'Jebel Ali Port', 'AEJEA', 'UAE', 24.9857, 55.0272, 'Jebel Ali Terminal 3', 'port_of_discharge', '4.0', now()->subDays(30)),
            $this->m($tid, 5, 'unload', 'transport', 'actual', 'Jebel Ali Port', 'AEJEA', 'UAE', 24.9857, 55.0272, 'Jebel Ali Terminal 3', 'port_of_discharge', '4.0', now()->subDays(30)),
            $this->m($tid, 6, 'gate_out', 'transport', 'actual', 'Jebel Ali Port', 'AEJEA', 'UAE', 24.9857, 55.0272, null, 'port_of_discharge', '4.0', now()->subDays(29)),
            $this->m($tid, 7, 'delivery', 'transport', 'actual', 'Al Quoz Industrial Area Warehouse', 'AEJEA', 'UAE', 25.1472, 55.2180, null, 'final_delivery', '4.0', now()->subDays(28)),
        ];
    }

    private function jnptToRotterdamMilestones(int $tid, $trip): array
    {
        return [
            $this->m($tid, 1, 'gate_in', 'transport', 'actual', 'JNPT CFS Gateway Terminal', 'INNSA', 'India', 18.9450, 72.9600, 'GTI Berth 7', 'port_of_loading', '5.5', now()->subDays(14)),
            $this->m($tid, 2, 'load', 'transport', 'actual', 'Jawaharlal Nehru Port (JNPT)', 'INNSA', 'India', 18.9488, 72.9511, 'GTI Berth 7', 'port_of_loading', '5.5', now()->subDays(13)),
            $this->m($tid, 3, 'departure', 'transport', 'actual', 'Jawaharlal Nehru Port (JNPT)', 'INNSA', 'India', 18.9488, 72.9511, null, 'port_of_loading', '5.5', now()->subDays(13)),
            $this->m($tid, 4, 'arrival', 'transport', 'planned', 'Suez Canal (Port Said)', 'EGPSD', 'Egypt', 31.2565, 32.2841, 'Suez Canal Authority', 'transshipment', '2.0', now()->addDays(4)),
            $this->m($tid, 5, 'departure', 'transport', 'planned', 'Suez Canal (Port Said)', 'EGPSD', 'Egypt', 31.2565, 32.2841, null, 'transshipment', '2.0', now()->addDays(5)),
            $this->m($tid, 6, 'arrival', 'transport', 'planned', 'Port of Rotterdam (ECT Delta)', 'NLRTM', 'Netherlands', 51.9244, 4.4777, 'ECT Delta Terminal', 'port_of_discharge', '1.0', now()->addDays(14)),
            $this->m($tid, 7, 'unload', 'transport', 'planned', 'Port of Rotterdam (ECT Delta)', 'NLRTM', 'Netherlands', 51.9244, 4.4777, 'ECT Delta Terminal', 'port_of_discharge', '1.0', now()->addDays(15)),
        ];
    }

    private function chennaiToHamburgMilestones(int $tid, $trip): array
    {
        $isCompleted = $trip?->status?->value === 'completed';

        return [
            $this->m($tid, 1, 'gate_in', 'transport', 'actual', 'Chennai Port CFS (CCTL)', 'INMAA', 'India', 13.0800, 80.2850, 'CCTL Terminal', 'port_of_loading', '5.5', now()->subDays(58)),
            $this->m($tid, 2, 'load', 'transport', 'actual', 'Chennai Port (Kamarajar)', 'INMAA', 'India', 13.0836, 80.2969, 'Bharati Dock', 'port_of_loading', '5.5', now()->subDays(57)),
            $this->m($tid, 3, 'departure', 'transport', 'actual', 'Chennai Port (Kamarajar)', 'INMAA', 'India', 13.0836, 80.2969, null, 'port_of_loading', '5.5', now()->subDays(57)),
            $this->m($tid, 4, 'arrival', 'transport', 'actual', 'Colombo Port (SLPA)', 'LKCMB', 'Sri Lanka', 6.9271, 79.8612, 'Jaye Container Terminal', 'transshipment', '5.5', now()->subDays(55)),
            $this->m($tid, 5, 'unload', 'transport', 'actual', 'Colombo Port (SLPA)', 'LKCMB', 'Sri Lanka', 6.9271, 79.8612, 'JCT Berth 4', 'transshipment', '5.5', now()->subDays(54)),
            $this->m($tid, 6, 'load', 'transport', 'actual', 'Colombo Port (SLPA)', 'LKCMB', 'Sri Lanka', 6.9271, 79.8612, 'JCT Berth 4', 'transshipment', '5.5', now()->subDays(53)),
            $this->m($tid, 7, 'departure', 'transport', 'actual', 'Colombo Port (SLPA)', 'LKCMB', 'Sri Lanka', 6.9271, 79.8612, null, 'transshipment', '5.5', now()->subDays(52)),
            $this->m($tid, 8, 'arrival', 'transport', 'actual', 'Suez Canal (Port Said)', 'EGPSD', 'Egypt', 31.2565, 32.2841, 'Suez Canal Authority', 'transshipment', '2.0', now()->subDays(42)),
            $this->m($tid, 9, 'departure', 'transport', 'actual', 'Suez Canal (Port Said)', 'EGPSD', 'Egypt', 31.2565, 32.2841, null, 'transshipment', '2.0', now()->subDays(41)),
            $this->m($tid, 10, 'arrival', 'transport', $isCompleted ? 'actual' : 'planned',
                'Port of Hamburg (HHLA)', 'DEHAM', 'Germany', 53.5439, 9.9569, 'HHLA CTA Terminal', 'port_of_discharge', '1.0', now()->subDays(28)),
            $this->m($tid, 11, 'unload', 'transport', $isCompleted ? 'actual' : 'planned',
                'Port of Hamburg (HHLA)', 'DEHAM', 'Germany', 53.5439, 9.9569, 'HHLA CTA Terminal', 'port_of_discharge', '1.0', now()->subDays(27)),
            $this->m($tid, 12, 'gate_out', 'transport', $isCompleted ? 'actual' : 'planned',
                'Port of Hamburg (HHLA)', 'DEHAM', 'Germany', 53.5439, 9.9569, null, 'port_of_discharge', '1.0', now()->subDays(26)),
        ];
    }

    private function jebelAliToIndiaMilestones(int $tid, $trip): array
    {
        $destPort = $trip?->destination_port_code === 'INMUN' ? 'Mundra Port (APSEZ)' : 'Jawaharlal Nehru Port (JNPT)';
        $destUnlocode = $trip?->destination_port_code ?? 'INNSA';
        $destLat = $trip?->destination_port_code === 'INMUN' ? 22.8381 : 18.9488;
        $destLng = $trip?->destination_port_code === 'INMUN' ? 69.7032 : 72.9511;
        $isArrived = in_array($trip?->status?->value, ['delivered', 'completed', 'active'], true);

        return [
            $this->m($tid, 1, 'gate_in', 'transport', 'actual', 'Jebel Ali Port (JAFZA)', 'AEJEA', 'UAE', 24.9857, 55.0272, 'Terminal 1', 'port_of_loading', '4.0', now()->subDays(20)),
            $this->m($tid, 2, 'load', 'transport', 'actual', 'Jebel Ali Port (JAFZA)', 'AEJEA', 'UAE', 24.9857, 55.0272, 'Terminal 1 Berth', 'port_of_loading', '4.0', now()->subDays(19)),
            $this->m($tid, 3, 'departure', 'transport', 'actual', 'Jebel Ali Port (JAFZA)', 'AEJEA', 'UAE', 24.9857, 55.0272, null, 'port_of_loading', '4.0', now()->subDays(19)),
            $this->m($tid, 4, 'arrival', 'transport', $isArrived ? 'actual' : 'planned', $destPort, $destUnlocode, 'India', $destLat, $destLng, null, 'port_of_discharge', '5.5', now()->subDays(14)),
            $this->m($tid, 5, 'unload', 'transport', $isArrived ? 'actual' : 'planned', $destPort, $destUnlocode, 'India', $destLat, $destLng, null, 'port_of_discharge', '5.5', now()->subDays(13)),
        ];
    }

    private function singaporeToIndiaMilestones(int $tid, $trip): array
    {
        $destPort = $trip?->destination_port_code === 'INMAA' ? 'Chennai Port (Kamarajar)' : 'Jawaharlal Nehru Port (JNPT)';
        $destUnlocode = $trip?->destination_port_code ?? 'INMAA';
        $destLat = $trip?->destination_port_code === 'INMAA' ? 13.0836 : 18.9488;
        $destLng = $trip?->destination_port_code === 'INMAA' ? 80.2969 : 72.9511;
        $isArrived = in_array($trip?->status?->value, ['active', 'delivered', 'completed'], true);

        return [
            $this->m($tid, 1, 'gate_in', 'transport', 'actual', 'Singapore PSA (Tanjong Pagar)', 'SGSIN', 'Singapore', 1.2566, 103.8198, 'Tanjong Pagar Terminal', 'port_of_loading', '8.0', now()->subDays(10)),
            $this->m($tid, 2, 'load', 'transport', 'actual', 'Singapore PSA (Tanjong Pagar)', 'SGSIN', 'Singapore', 1.2566, 103.8198, 'TPT Berth 12', 'port_of_loading', '8.0', now()->subDays(9)),
            $this->m($tid, 3, 'departure', 'transport', 'actual', 'Singapore PSA (Tanjong Pagar)', 'SGSIN', 'Singapore', 1.2566, 103.8198, null, 'port_of_loading', '8.0', now()->subDays(9)),
            $this->m($tid, 4, 'arrival', 'transport', $isArrived ? 'actual' : 'planned', $destPort, $destUnlocode, 'India', $destLat, $destLng, null, 'port_of_discharge', '5.5', now()->subDays($isArrived ? 2 : -3)),
            $this->m($tid, 5, 'unload', 'transport', $isArrived ? 'actual' : 'planned', $destPort, $destUnlocode, 'India', $destLat, $destLng, null, 'port_of_discharge', '5.5', now()->subDays($isArrived ? 1 : -4)),
        ];
    }

    private function shanghaiToChennaiMilestones(int $tid, $trip): array
    {
        return [
            $this->m($tid, 1, 'gate_in', 'transport', 'actual', 'Yangshan Deep Water Port', 'CNSHA', 'China', 30.6218, 122.0580, 'Yangshan Terminal 4', 'port_of_loading', '8.0', now()->subDays(18)),
            $this->m($tid, 2, 'load', 'transport', 'actual', 'Yangshan Deep Water Port', 'CNSHA', 'China', 30.6218, 122.0580, 'Yangshan T4 Berth 8', 'port_of_loading', '8.0', now()->subDays(17)),
            $this->m($tid, 3, 'departure', 'transport', 'actual', 'Yangshan Deep Water Port', 'CNSHA', 'China', 30.6218, 122.0580, null, 'port_of_loading', '8.0', now()->subDays(17)),
            $this->m($tid, 4, 'arrival', 'transport', 'planned', 'Singapore PSA (transshipment)', 'SGSIN', 'Singapore', 1.2566, 103.8198, null, 'transshipment', '8.0', now()->addDays(1)),
            $this->m($tid, 5, 'departure', 'transport', 'planned', 'Singapore PSA (transshipment)', 'SGSIN', 'Singapore', 1.2566, 103.8198, null, 'transshipment', '8.0', now()->addDays(2)),
            $this->m($tid, 6, 'arrival', 'transport', 'planned', 'Chennai Port (Kamarajar)', 'INMAA', 'India', 13.0836, 80.2969, 'Bharati Dock', 'port_of_discharge', '5.5', now()->addDays(4)),
            $this->m($tid, 7, 'unload', 'transport', 'planned', 'Chennai Port (Kamarajar)', 'INMAA', 'India', 13.0836, 80.2969, 'Bharati Dock', 'port_of_discharge', '5.5', now()->addDays(5)),
        ];
    }

    private function jnptToSingaporeViaColomboMilestones(int $tid, $trip): array
    {
        return [
            $this->m($tid, 1, 'gate_in', 'transport', 'actual', 'JNPT CFS', 'INNSA', 'India', 18.9450, 72.9600, 'GTI', 'port_of_loading', '5.5', now()->subDays(22)),
            $this->m($tid, 2, 'load', 'transport', 'actual', 'Jawaharlal Nehru Port (JNPT)', 'INNSA', 'India', 18.9488, 72.9511, 'GTI Berth 6', 'port_of_loading', '5.5', now()->subDays(21)),
            $this->m($tid, 3, 'departure', 'transport', 'actual', 'Jawaharlal Nehru Port (JNPT)', 'INNSA', 'India', 18.9488, 72.9511, null, 'port_of_loading', '5.5', now()->subDays(21)),
            $this->m($tid, 4, 'arrival', 'transport', 'actual', 'Colombo Port (transshipment)', 'LKCMB', 'Sri Lanka', 6.9271, 79.8612, 'JCT Terminal', 'transshipment', '5.5', now()->subDays(18)),
            $this->m($tid, 5, 'unload', 'transport', 'actual', 'Colombo Port (transshipment)', 'LKCMB', 'Sri Lanka', 6.9271, 79.8612, 'JCT Berth 3', 'transshipment', '5.5', now()->subDays(17)),
            $this->m($tid, 6, 'load', 'transport', 'actual', 'Colombo Port (transshipment)', 'LKCMB', 'Sri Lanka', 6.9271, 79.8612, 'JCT Berth 3', 'transshipment', '5.5', now()->subDays(16)),
            $this->m($tid, 7, 'departure', 'transport', 'actual', 'Colombo Port (transshipment)', 'LKCMB', 'Sri Lanka', 6.9271, 79.8612, null, 'transshipment', '5.5', now()->subDays(15)),
            $this->m($tid, 8, 'arrival', 'transport', 'planned', 'Port of Singapore (PSA)', 'SGSIN', 'Singapore', 1.2566, 103.8198, 'Brani Terminal', 'port_of_discharge', '8.0', now()->addDays(8)),
            $this->m($tid, 9, 'unload', 'transport', 'planned', 'Port of Singapore (PSA)', 'SGSIN', 'Singapore', 1.2566, 103.8198, 'Brani Terminal', 'port_of_discharge', '8.0', now()->addDays(9)),
        ];
    }

    private function genericMilestones(int $tid, $trip): array
    {
        $polName = $trip?->origin_port_name ?? 'Origin Port';
        $polUnlocode = $trip?->origin_port_code ?? 'INNSA';
        $podName = $trip?->destination_port_name ?? 'Destination Port';
        $podUnlocode = $trip?->destination_port_code ?? 'UNKWN';

        return [
            $this->m($tid, 1, 'load', 'transport', 'actual', $polName, $polUnlocode, 'India', null, null, null, 'port_of_loading', '5.5', now()->subDays(20)),
            $this->m($tid, 2, 'departure', 'transport', 'actual', $polName, $polUnlocode, 'India', null, null, null, 'port_of_loading', '5.5', now()->subDays(19)),
            $this->m($tid, 3, 'arrival', 'transport', 'planned', $podName, $podUnlocode, null, null, null, null, 'port_of_discharge', '5.5', now()->addDays(10)),
            $this->m($tid, 4, 'unload', 'transport', 'planned', $podName, $podUnlocode, null, null, null, null, 'port_of_discharge', '5.5', now()->addDays(11)),
        ];
    }

    // ── Milestone row builder ─────────────────────────────────────────────────

    private function m(
        int    $tripId, int $seq, string $eventType, string $category, string $classifier,
        string $locName, string $locUnlocode, ?string $country,
        ?float $lat, ?float $lng, ?string $terminal,
        string $locationType, ?string $localOffset, $occurredAt
    ): array {
        return [
            'mt_event_id' => "EVT-{$tripId}-" . str_pad($seq, 3, '0', STR_PAD_LEFT),
            'event_type' => $eventType,
            'event_category' => $category,
            'event_classifier' => $classifier,
            'location_name' => $locName,
            'location_unlocode' => $locUnlocode,
            'location_country' => $country,
            'lat' => $lat,
            'lng' => $lng,
            'terminal_name' => $terminal,
            'location_type' => $locationType,
            'local_time_offset' => $localOffset,
            'sequence' => $seq,
            'occurred_at' => $occurredAt,
        ];
    }

    /**
     * Synthetic MMSI generator — in production the MMSI is supplied by the AIS feed.
     * We derive a stable 9-digit MMSI from the IMO so re-running the seeder is idempotent.
     */
    private function mmsiFromImo(?string $imo): ?string
    {
        if (!$imo) return null;
        // Strip non-digits
        $digits = preg_replace('/[^0-9]/', '', $imo);
        if (!$digits) return null;
        // Pad / trim to 9 digits — last 9 of the IMO hash
        $seed = (int) substr(preg_replace('/[^0-9]/', '', md5($digits)), 0, 9);
        return str_pad((string) ($seed % 1_000_000_000), 9, '0', STR_PAD_LEFT);
    }
}
