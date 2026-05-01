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

        foreach ($customers as $customer) {
            foreach (Trip::where('customer_id', $customer->id)->get() as $trip) {
                if (TripTrackingPoint::where('trip_id', $trip->id)->exists()) continue;
                if ($trip->status === TripStatus::Draft) continue;

                $points = $this->pointsFor($trip);
                if (empty($points)) continue;

                foreach ($points as $p) {
                    TripTrackingPoint::insert([
                        'trip_id' => $trip->id,
                        'customer_id' => $trip->customer_id,
                        'source' => $p['source'],
                        'lat' => $p['lat'],
                        'lng' => $p['lng'],
                        'speed' => $p['speed'] ?? null,
                        'heading' => $p['heading'] ?? null,
                        'accuracy' => $p['accuracy'] ?? null,
                        'location_name' => $p['location_name'] ?? null,
                        'external_id' => $p['external_id'] ?? null,
                        'recorded_at' => $p['recorded_at'],
                        'raw_payload' => json_encode(['seeded' => true, 'source' => $p['source']]),
                        'created_at' => $p['recorded_at'],
                    ]);
                    $total++;
                }

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

    // ── Route tracking data ───────────────────────────────────────────────────

    private function pointsFor(Trip $trip): array
    {
        return match ($trip->trip_ref) {

            // ── VL-T01: Completed Export Multimodal (Delhi→JNPT road + JNPT→Jebel Ali sea)
            'TR_VL_T01' => array_merge(
                $this->delhiToJnptRoad($trip, now()->subDays(45)),
                $this->jnptToJebelAliSea($trip, now()->subDays(38))
            ),

            // ── VL-T02: Completed Import Multimodal (Jebel Ali sea → JNPT → Delhi road)
            'TR_VL_T02' => array_merge(
                $this->jebelAliToJnptSea($trip, now()->subDays(50)),
                $this->jnptToDelhiRoad($trip, now()->subDays(33))
            ),

            // ── VL-T03: Active InTransit — Delhi→JNPT, currently near Vadodara
            'TR_VL_T03' => $this->delhiToJnptRoadPartial($trip, now()->subDays(3)),

            // ── VL-T04: AtPort — Full Delhi→JNPT road, arrived
            'TR_VL_T04' => $this->delhiToJnptRoad($trip, now()->subDays(8), arriveAtEnd: true),

            // ── VL-T05: OnVessel — JNPT→Rotterdam, currently in Red Sea
            'TR_VL_T05' => $this->jnptToRotterdamSeaPartial($trip, now()->subDays(15)),

            // ── VL-T06: InTransshipment — JNPT→Colombo reached, waiting for next vessel
            'TR_VL_T06' => $this->jnptToColomboDone($trip, now()->subDays(22)),

            // ── VL-T07: VesselArrived — Jebel Ali→Mundra sea, arrived
            'TR_VL_T07' => $this->jebelAliToMundraSea($trip, now()->subDays(20)),

            // ── VL-T08: OutForDelivery — JNPT→Delhi road, currently near Agra
            'TR_VL_T08' => $this->jnptToDelhiRoadPartial($trip, now()->subDays(4)),

            // ── VL-T09: Delivered — Singapore→JNPT sea + JNPT→Delhi road, complete
            'TR_VL_T09' => array_merge(
                $this->singaporeToJnptSea($trip, now()->subDays(16)),
                $this->jnptToDelhiRoad($trip, now()->subDays(10))
            ),

            // ── VL-T10: Completed Domestic — Delhi→Mumbai full
            'TR_VL_T10' => $this->delhiToMumbaiRoad($trip, now()->subDays(30)),

            // ── II-T01: Completed Export Sea — Chennai→Hamburg (via Colombo+Suez)
            'TR_II_T01' => $this->chennaiToHamburgSea($trip, now()->subDays(60)),

            // ── II-T02: OnVessel — Shanghai→Chennai, currently Bay of Bengal
            'TR_II_T02' => $this->shanghaiToChennaiSeaPartial($trip, now()->subDays(20)),

            // ── II-T03: InTransit — Chennai Factory→Chennai Port (short road leg)
            'TR_II_T03' => $this->chennaiFactoryToPort($trip, now()->subDays(1)),

            // ── II-T04: Completed Domestic — Chennai→Bengaluru
            'TR_II_T04' => $this->chennaiToBengaluruRoad($trip, now()->subDays(16)),

            // ── II-T05: AtPort — Singapore→Chennai sea, arrived at port
            'TR_II_T05' => $this->singaporeToChennaiSea($trip, now()->subDays(10)),

            // ── II-T06: VesselArrived — Singapore→Chennai arrived
            'TR_II_T06' => $this->singaporeToChennaiSea($trip, now()->subDays(14)),

            // ── II-T08: Active Domestic — Chennai→Delhi, currently near Hyderabad
            'TR_II_T08' => $this->chennaiToDelhiRoadPartial($trip, now()->subDays(2)),

            default => [],
        };
    }

    // ── Road route: Delhi → JNPT (~1380km via NH-48) ─────────────────────────

    private function delhiToJnptRoad(Trip $trip, Carbon $start, bool $arriveAtEnd = false): array
    {
        // Full route with toll plazas
        $waypoints = [
            // Departure
            ['lat' => 28.6358, 'lng' => 77.1022, 'name' => null, 'toll' => false],
            // Toll: Kherki Daula, Gurgaon (NH-48)
            ['lat' => 28.3843, 'lng' => 76.9478, 'name' => 'Kherki Daula Toll Plaza, Gurgaon', 'toll' => true],
            // Toll: Shahjahanpur, Rajasthan
            ['lat' => 27.8863, 'lng' => 76.2832, 'name' => 'Shahjahanpur Toll, Rajasthan', 'toll' => true],
            // Toll: Jaipur Ring Road
            ['lat' => 26.8231, 'lng' => 75.8189, 'name' => 'Jaipur SPR Toll Plaza', 'toll' => true],
            // Toll: Kishangarh, Ajmer
            ['lat' => 26.5988, 'lng' => 74.8622, 'name' => 'Kishangarh Toll, Ajmer', 'toll' => true],
            // Toll: Chittorgarh
            ['lat' => 24.8882, 'lng' => 74.6391, 'name' => 'Chittorgarh Toll', 'toll' => true],
            // Toll: Udaipur
            ['lat' => 24.5700, 'lng' => 73.7200, 'name' => 'Udaipur Toll', 'toll' => true],
            // Toll: Halol, Gujarat
            ['lat' => 22.5048, 'lng' => 73.4740, 'name' => 'Halol Toll Plaza, Gujarat', 'toll' => true],
            // Toll: Vadodara
            ['lat' => 22.3072, 'lng' => 73.1812, 'name' => 'Vadodara Expressway Toll', 'toll' => true],
            // Toll: Kim (Surat approach)
            ['lat' => 21.2200, 'lng' => 72.8700, 'name' => 'Kim-Mandvi Toll, Surat', 'toll' => true],
            // Toll: Virar (Mumbai approach)
            ['lat' => 19.4668, 'lng' => 72.8121, 'name' => 'Virar Toll Plaza', 'toll' => true],
            // Toll: Dahisar, Mumbai
            ['lat' => 19.2535, 'lng' => 72.8529, 'name' => 'Dahisar Toll Naka, Mumbai', 'toll' => true],
            // Toll: Vashi Bridge
            ['lat' => 19.0625, 'lng' => 73.0085, 'name' => 'Vashi Toll Plaza', 'toll' => true],
            // Toll: Panvel (JNPT approach)
            ['lat' => 18.9896, 'lng' => 73.1169, 'name' => 'Panvel Toll Plaza', 'toll' => true],
            // Arrival: JNPT Gate
            ['lat' => 18.9488, 'lng' => 72.9511, 'name' => 'JNPT Main Gate (CFS Entry)', 'toll' => false],
        ];

        return $this->buildRoadPoints($waypoints, $start, 4.0);
    }

    private function delhiToJnptRoadPartial(Trip $trip, Carbon $start): array
    {
        // Only first ~8 waypoints (stopped near Vadodara)
        $waypoints = [
            ['lat' => 28.6358, 'lng' => 77.1022, 'name' => null, 'toll' => false],
            ['lat' => 28.3843, 'lng' => 76.9478, 'name' => 'Kherki Daula Toll Plaza, Gurgaon', 'toll' => true],
            ['lat' => 27.8863, 'lng' => 76.2832, 'name' => 'Shahjahanpur Toll, Rajasthan', 'toll' => true],
            ['lat' => 26.8231, 'lng' => 75.8189, 'name' => 'Jaipur SPR Toll Plaza', 'toll' => true],
            ['lat' => 26.5988, 'lng' => 74.8622, 'name' => 'Kishangarh Toll, Ajmer', 'toll' => true],
            ['lat' => 24.8882, 'lng' => 74.6391, 'name' => 'Chittorgarh Toll', 'toll' => true],
            ['lat' => 24.5700, 'lng' => 73.7200, 'name' => 'Udaipur Toll', 'toll' => true],
            // Currently near Vadodara
            ['lat' => 22.3072, 'lng' => 73.1812, 'name' => 'Vadodara Expressway Toll', 'toll' => true],
        ];

        return $this->buildRoadPoints($waypoints, $start, 4.0);
    }

    // ── Road route: JNPT → Delhi (~1380km via NH-48) ─────────────────────────

    private function jnptToDelhiRoad(Trip $trip, Carbon $start): array
    {
        $waypoints = [
            ['lat' => 18.9488, 'lng' => 72.9511, 'name' => 'JNPT CFS Exit Gate', 'toll' => false],
            ['lat' => 18.9896, 'lng' => 73.1169, 'name' => 'Panvel Toll Plaza', 'toll' => true],
            ['lat' => 19.0625, 'lng' => 73.0085, 'name' => 'Vashi Toll Plaza', 'toll' => true],
            ['lat' => 19.2535, 'lng' => 72.8529, 'name' => 'Dahisar Toll Naka, Mumbai', 'toll' => true],
            ['lat' => 19.4668, 'lng' => 72.8121, 'name' => 'Virar Toll Plaza', 'toll' => true],
            ['lat' => 21.2200, 'lng' => 72.8700, 'name' => 'Kim-Mandvi Toll, Surat', 'toll' => true],
            ['lat' => 22.3072, 'lng' => 73.1812, 'name' => 'Vadodara Expressway Toll', 'toll' => true],
            ['lat' => 22.5048, 'lng' => 73.4740, 'name' => 'Halol Toll Plaza, Gujarat', 'toll' => true],
            ['lat' => 24.5700, 'lng' => 73.7200, 'name' => 'Udaipur Toll', 'toll' => true],
            ['lat' => 24.8882, 'lng' => 74.6391, 'name' => 'Chittorgarh Toll', 'toll' => true],
            ['lat' => 26.5988, 'lng' => 74.8622, 'name' => 'Kishangarh Toll, Ajmer', 'toll' => true],
            ['lat' => 26.8231, 'lng' => 75.8189, 'name' => 'Jaipur SPR Toll Plaza', 'toll' => true],
            ['lat' => 27.8863, 'lng' => 76.2832, 'name' => 'Shahjahanpur Toll, Rajasthan', 'toll' => true],
            ['lat' => 28.3843, 'lng' => 76.9478, 'name' => 'Kherki Daula Toll, Gurgaon', 'toll' => true],
            ['lat' => 28.5706, 'lng' => 77.3522, 'name' => null, 'toll' => false],
        ];

        return $this->buildRoadPoints($waypoints, $start, 4.0);
    }

    private function jnptToDelhiRoadPartial(Trip $trip, Carbon $start): array
    {
        // Currently near Agra — roughly 60% of the journey done
        $waypoints = [
            ['lat' => 18.9488, 'lng' => 72.9511, 'name' => 'JNPT CFS Exit Gate', 'toll' => false],
            ['lat' => 18.9896, 'lng' => 73.1169, 'name' => 'Panvel Toll Plaza', 'toll' => true],
            ['lat' => 19.2535, 'lng' => 72.8529, 'name' => 'Dahisar Toll Naka, Mumbai', 'toll' => true],
            ['lat' => 21.2200, 'lng' => 72.8700, 'name' => 'Kim-Mandvi Toll, Surat', 'toll' => true],
            ['lat' => 22.3072, 'lng' => 73.1812, 'name' => 'Vadodara Expressway Toll', 'toll' => true],
            ['lat' => 24.5700, 'lng' => 73.7200, 'name' => 'Udaipur Toll', 'toll' => true],
            ['lat' => 24.8882, 'lng' => 74.6391, 'name' => 'Chittorgarh Toll', 'toll' => true],
            ['lat' => 26.5988, 'lng' => 74.8622, 'name' => 'Kishangarh Toll', 'toll' => true],
            // Currently near Agra
            ['lat' => 27.1767, 'lng' => 78.0081, 'name' => 'Agra Toll Plaza (NH-19)', 'toll' => true],
        ];

        return $this->buildRoadPoints($waypoints, $start, 4.0);
    }

    // ── Road route: Delhi → Mumbai via NH-48 ─────────────────────────────────

    private function delhiToMumbaiRoad(Trip $trip, Carbon $start): array
    {
        $waypoints = [
            ['lat' => 28.6358, 'lng' => 77.1022, 'name' => null, 'toll' => false],
            ['lat' => 28.3843, 'lng' => 76.9478, 'name' => 'Kherki Daula Toll Plaza, Gurgaon', 'toll' => true],
            ['lat' => 27.8863, 'lng' => 76.2832, 'name' => 'Shahjahanpur Toll', 'toll' => true],
            ['lat' => 26.8231, 'lng' => 75.8189, 'name' => 'Jaipur SPR Toll', 'toll' => true],
            ['lat' => 24.8882, 'lng' => 74.6391, 'name' => 'Chittorgarh Toll', 'toll' => true],
            ['lat' => 23.0225, 'lng' => 72.5714, 'name' => 'Ahmedabad Tollway', 'toll' => true],
            ['lat' => 22.3072, 'lng' => 73.1812, 'name' => 'Vadodara Expressway Toll', 'toll' => true],
            ['lat' => 21.1702, 'lng' => 72.8311, 'name' => 'Surat (Pal) Toll', 'toll' => true],
            ['lat' => 19.4668, 'lng' => 72.8121, 'name' => 'Virar Toll Plaza', 'toll' => true],
            ['lat' => 19.2963, 'lng' => 73.0517, 'name' => null, 'toll' => false],
        ];

        return $this->buildRoadPoints($waypoints, $start, 4.0);
    }

    // ── Road route: Chennai Factory → Chennai Port (~20km) ───────────────────

    private function chennaiFactoryToPort(Trip $trip, Carbon $start): array
    {
        $waypoints = [
            ['lat' => 13.1156, 'lng' => 80.1551, 'name' => null, 'toll' => false],
            ['lat' => 13.1100, 'lng' => 80.2200, 'name' => 'Royapuram Junction', 'toll' => false],
            ['lat' => 13.0930, 'lng' => 80.2700, 'name' => 'Parrys Corner, George Town', 'toll' => false],
            ['lat' => 13.0836, 'lng' => 80.2969, 'name' => 'Chennai Port Gate 3 (Kamarajar Port)', 'toll' => false],
        ];

        return $this->buildRoadPoints($waypoints, $start, 1.0);
    }

    // ── Road route: Chennai → Bengaluru (~350km via NH-44) ───────────────────

    private function chennaiToBengaluruRoad(Trip $trip, Carbon $start): array
    {
        $waypoints = [
            ['lat' => 13.1156, 'lng' => 80.1551, 'name' => null, 'toll' => false],
            ['lat' => 12.9165, 'lng' => 79.1325, 'name' => 'Vellore Toll Plaza (NH-44)', 'toll' => true],
            ['lat' => 12.7409, 'lng' => 77.8253, 'name' => 'Hosur Toll (Karnataka border)', 'toll' => true],
            ['lat' => 12.8441, 'lng' => 77.6689, 'name' => null, 'toll' => false],
        ];

        return $this->buildRoadPoints($waypoints, $start, 2.0);
    }

    // ── Road route: Chennai → Delhi partial (~NH-44, near Hyderabad) ─────────

    private function chennaiToDelhiRoadPartial(Trip $trip, Carbon $start): array
    {
        $waypoints = [
            ['lat' => 13.1156, 'lng' => 80.1551, 'name' => null, 'toll' => false],
            ['lat' => 12.9165, 'lng' => 79.1325, 'name' => 'Vellore Toll (NH-44)', 'toll' => true],
            ['lat' => 12.5266, 'lng' => 78.2137, 'name' => 'Krishnagiri Toll', 'toll' => true],
            ['lat' => 13.0827, 'lng' => 77.5877, 'name' => 'Bengaluru Ring Road Toll', 'toll' => true],
            ['lat' => 14.6819, 'lng' => 77.6006, 'name' => 'Anantapur Toll (AP)', 'toll' => true],
            ['lat' => 15.8281, 'lng' => 78.0373, 'name' => 'Kurnool Toll', 'toll' => true],
            // Currently near Hyderabad
            ['lat' => 17.3850, 'lng' => 78.4867, 'name' => 'Hyderabad Outer Ring Road Toll', 'toll' => true],
        ];

        return $this->buildRoadPoints($waypoints, $start, 3.0);
    }

    // ── Sea route: JNPT → Jebel Ali (~2300km, Arabian Sea) ───────────────────

    private function jnptToJebelAliSea(Trip $trip, Carbon $start): array
    {
        $waypoints = [
            ['lat' => 18.9488, 'lng' => 72.9511, 'label' => 'JNPT departure'],
            ['lat' => 16.9902, 'lng' => 73.2994, 'label' => 'Off Ratnagiri coast'],
            ['lat' => 15.3716, 'lng' => 73.8546, 'label' => 'Off Goa coast'],
            ['lat' => 12.8698, 'lng' => 74.8427, 'label' => 'Off Mangalore coast'],
            ['lat' => 11.5000, 'lng' => 71.5000, 'label' => 'Laccadive Sea'],
            ['lat' => 13.5000, 'lng' => 66.0000, 'label' => 'Arabian Sea (mid)'],
            ['lat' => 16.0000, 'lng' => 63.0000, 'label' => 'Arabian Sea (northwest)'],
            ['lat' => 19.0000, 'lng' => 60.5000, 'label' => 'Off Oman coast'],
            ['lat' => 22.0000, 'lng' => 60.0000, 'label' => 'Gulf of Oman approach'],
            ['lat' => 25.0000, 'lng' => 57.0000, 'label' => 'Strait of Hormuz'],
            ['lat' => 24.9857, 'lng' => 55.0272, 'label' => 'Jebel Ali Port arrival'],
        ];

        return $this->buildSeaPoints($waypoints, $start, 3.5);
    }

    // ── Sea route: Jebel Ali → JNPT ──────────────────────────────────────────

    private function jebelAliToJnptSea(Trip $trip, Carbon $start): array
    {
        $waypoints = [
            ['lat' => 24.9857, 'lng' => 55.0272, 'label' => 'Jebel Ali Port departure'],
            ['lat' => 25.0000, 'lng' => 57.0000, 'label' => 'Strait of Hormuz'],
            ['lat' => 22.0000, 'lng' => 60.0000, 'label' => 'Gulf of Oman'],
            ['lat' => 18.5000, 'lng' => 62.0000, 'label' => 'Arabian Sea (north)'],
            ['lat' => 16.0000, 'lng' => 65.0000, 'label' => 'Arabian Sea (mid)'],
            ['lat' => 13.0000, 'lng' => 68.0000, 'label' => 'Arabian Sea (east)'],
            ['lat' => 11.5000, 'lng' => 71.5000, 'label' => 'Laccadive Sea'],
            ['lat' => 14.0000, 'lng' => 73.5000, 'label' => 'Off Karnataka coast'],
            ['lat' => 16.5000, 'lng' => 73.2000, 'label' => 'Off Ratnagiri'],
            ['lat' => 18.9488, 'lng' => 72.9511, 'label' => 'JNPT arrival'],
        ];

        return $this->buildSeaPoints($waypoints, $start, 3.5);
    }

    // ── Sea route: Jebel Ali → Mundra ────────────────────────────────────────

    private function jebelAliToMundraSea(Trip $trip, Carbon $start): array
    {
        $waypoints = [
            ['lat' => 24.9857, 'lng' => 55.0272, 'label' => 'Jebel Ali Port departure'],
            ['lat' => 25.0000, 'lng' => 57.0000, 'label' => 'Strait of Hormuz'],
            ['lat' => 23.0000, 'lng' => 60.0000, 'label' => 'Gulf of Oman'],
            ['lat' => 22.5000, 'lng' => 65.0000, 'label' => 'Arabian Sea'],
            ['lat' => 22.8381, 'lng' => 69.7032, 'label' => 'Mundra Port arrival'],
        ];

        return $this->buildSeaPoints($waypoints, $start, 2.5);
    }

    // ── Sea route: JNPT → Rotterdam via Suez Canal (partial — at Red Sea) ────

    private function jnptToRotterdamSeaPartial(Trip $trip, Carbon $start): array
    {
        $waypoints = [
            ['lat' => 18.9488, 'lng' => 72.9511, 'label' => 'JNPT departure'],
            ['lat' => 16.0000, 'lng' => 71.0000, 'label' => 'Arabian Sea'],
            ['lat' => 12.5000, 'lng' => 67.0000, 'label' => 'Arabian Sea (southwest)'],
            ['lat' => 11.5000, 'lng' => 52.0000, 'label' => 'Gulf of Aden'],
            ['lat' => 12.5000, 'lng' => 44.0000, 'label' => 'Red Sea (south — Djibouti area)'],
            // Currently here:
            ['lat' => 18.0000, 'lng' => 40.5000, 'label' => 'Red Sea (mid — off Saudi coast)'],
        ];

        return $this->buildSeaPoints($waypoints, $start, 3.5);
    }

    // ── Sea route: JNPT → Colombo complete ───────────────────────────────────

    private function jnptToColomboDone(Trip $trip, Carbon $start): array
    {
        $waypoints = [
            ['lat' => 18.9488, 'lng' => 72.9511, 'label' => 'JNPT departure'],
            ['lat' => 16.0000, 'lng' => 73.5000, 'label' => 'Off Karnataka coast'],
            ['lat' => 11.0000, 'lng' => 77.0000, 'label' => 'Gulf of Mannar approach'],
            ['lat' => 8.5000, 'lng' => 79.0000, 'label' => 'Palk Strait'],
            ['lat' => 6.9271, 'lng' => 79.8612, 'label' => 'Colombo Port — transshipment'],
        ];

        return $this->buildSeaPoints($waypoints, $start, 2.0);
    }

    // ── Sea route: Chennai → Hamburg via Suez (complete) ─────────────────────

    private function chennaiToHamburgSea(Trip $trip, Carbon $start): array
    {
        $waypoints = [
            ['lat' => 13.0836, 'lng' => 80.2969, 'label' => 'Chennai Port departure'],
            ['lat' => 8.5000, 'lng' => 79.0000, 'label' => 'Palk Strait'],
            ['lat' => 6.9271, 'lng' => 79.8612, 'label' => 'Colombo transshipment'],
            ['lat' => 5.0000, 'lng' => 75.0000, 'label' => 'Indian Ocean (north)'],
            ['lat' => 10.0000, 'lng' => 58.0000, 'label' => 'Gulf of Aden approach'],
            ['lat' => 11.5000, 'lng' => 52.0000, 'label' => 'Gulf of Aden'],
            ['lat' => 13.0000, 'lng' => 44.5000, 'label' => 'Red Sea (Djibouti)'],
            ['lat' => 18.0000, 'lng' => 41.0000, 'label' => 'Red Sea (mid)'],
            ['lat' => 24.0000, 'lng' => 37.5000, 'label' => 'Red Sea (north)'],
            ['lat' => 29.9200, 'lng' => 32.5500, 'label' => 'Suez Canal (Port Said entry)'],
            ['lat' => 31.5000, 'lng' => 28.0000, 'label' => 'Mediterranean Sea (east)'],
            ['lat' => 35.5000, 'lng' => 19.0000, 'label' => 'Mediterranean Sea (mid)'],
            ['lat' => 38.0000, 'lng' => 10.0000, 'label' => 'Mediterranean Sea (west)'],
            ['lat' => 35.9000, 'lng' => -5.4000, 'label' => 'Strait of Gibraltar'],
            ['lat' => 45.0000, 'lng' => -8.0000, 'label' => 'Bay of Biscay'],
            ['lat' => 49.5000, 'lng' => -1.5000, 'label' => 'English Channel approach'],
            ['lat' => 51.5000, 'lng' => 3.5000, 'label' => 'North Sea (south)'],
            ['lat' => 53.5439, 'lng' => 9.9569, 'label' => 'Hamburg Port (HHLA) arrival'],
        ];

        return $this->buildSeaPoints($waypoints, $start, 5.5);
    }

    // ── Sea route: Shanghai → Chennai (partial — Bay of Bengal) ──────────────

    private function shanghaiToChennaiSeaPartial(Trip $trip, Carbon $start): array
    {
        $waypoints = [
            ['lat' => 30.6218, 'lng' => 122.0580, 'label' => 'Shanghai Yangshan Port departure'],
            ['lat' => 25.0000, 'lng' => 121.5000, 'label' => 'East China Sea'],
            ['lat' => 20.0000, 'lng' => 120.0000, 'label' => 'Luzon Strait'],
            ['lat' => 14.0000, 'lng' => 113.0000, 'label' => 'South China Sea'],
            ['lat' => 4.0000, 'lng' => 101.0000, 'label' => 'Strait of Malacca'],
            ['lat' => 2.5000, 'lng' => 96.0000, 'label' => 'Malacca Strait (north)'],
            ['lat' => 7.5000, 'lng' => 95.0000, 'label' => 'Andaman Sea'],
            // Currently here:
            ['lat' => 12.5000, 'lng' => 84.0000, 'label' => 'Bay of Bengal (approaching Chennai)'],
        ];

        return $this->buildSeaPoints($waypoints, $start, 3.0);
    }

    // ── Sea route: Singapore → Chennai (complete) ─────────────────────────────

    private function singaporeToChennaiSea(Trip $trip, Carbon $start): array
    {
        $waypoints = [
            ['lat' => 1.2566, 'lng' => 103.8198, 'label' => 'Singapore PSA departure'],
            ['lat' => 3.5000, 'lng' => 98.0000, 'label' => 'Malacca Strait'],
            ['lat' => 7.5000, 'lng' => 94.0000, 'label' => 'Andaman Sea'],
            ['lat' => 10.0000, 'lng' => 88.0000, 'label' => 'Bay of Bengal'],
            ['lat' => 12.0000, 'lng' => 83.5000, 'label' => 'Bay of Bengal (east of Sri Lanka)'],
            ['lat' => 13.0836, 'lng' => 80.2969, 'label' => 'Chennai Port arrival'],
        ];

        return $this->buildSeaPoints($waypoints, $start, 2.5);
    }

    // ── Sea route: Singapore → JNPT ──────────────────────────────────────────

    private function singaporeToJnptSea(Trip $trip, Carbon $start): array
    {
        $waypoints = [
            ['lat' => 1.2566, 'lng' => 103.8198, 'label' => 'Singapore PSA departure'],
            ['lat' => 3.5000, 'lng' => 98.0000, 'label' => 'Malacca Strait'],
            ['lat' => 7.5000, 'lng' => 93.0000, 'label' => 'Andaman Sea'],
            ['lat' => 9.0000, 'lng' => 87.0000, 'label' => 'Bay of Bengal (north)'],
            ['lat' => 12.0000, 'lng' => 82.0000, 'label' => 'Bay of Bengal (west)'],
            ['lat' => 14.5000, 'lng' => 77.0000, 'label' => 'Off east coast India'],
            ['lat' => 17.5000, 'lng' => 74.5000, 'label' => 'Off Goa coast'],
            ['lat' => 18.9488, 'lng' => 72.9511, 'label' => 'JNPT arrival'],
        ];

        return $this->buildSeaPoints($waypoints, $start, 3.0);
    }

    // ── Builders ──────────────────────────────────────────────────────────────

    private function buildRoadPoints(array $waypoints, Carbon $startTime, float $hoursPerLeg): array
    {
        $points = [];
        foreach ($waypoints as $i => $wp) {
            $recordedAt = (clone $startTime)->addHours($i * $hoursPerLeg);

            if ($wp['toll']) {
                // FastTag event at toll
                $points[] = [
                    'source' => 'fast_tag',
                    'lat' => $wp['lat'] + (rand(-5, 5) / 10000),
                    'lng' => $wp['lng'] + (rand(-5, 5) / 10000),
                    'speed' => rand(20, 60),
                    'heading' => $this->bearingToNext($waypoints, $i),
                    'accuracy' => null,
                    'location_name' => $wp['name'],
                    'external_id' => 'FT' . substr(md5($wp['name'] . $startTime->timestamp . $i), 0, 12),
                    'recorded_at' => $recordedAt,
                ];

                // GPS mobile ping shortly after toll
                $points[] = [
                    'source' => 'driver_mobile',
                    'lat' => $wp['lat'] + (rand(-20, 20) / 10000),
                    'lng' => $wp['lng'] + (rand(-20, 20) / 10000),
                    'speed' => rand(50, 85),
                    'heading' => $this->bearingToNext($waypoints, $i),
                    'accuracy' => rand(5, 25),
                    'location_name' => null,
                    'external_id' => null,
                    'recorded_at' => (clone $recordedAt)->addMinutes(rand(5, 20)),
                ];
            } else {
                // Regular GPS ping at dispatch/delivery
                $points[] = [
                    'source' => 'driver_mobile',
                    'lat' => $wp['lat'],
                    'lng' => $wp['lng'],
                    'speed' => $i === 0 ? 0 : rand(0, 20), // starting or stopped
                    'heading' => $this->bearingToNext($waypoints, $i),
                    'accuracy' => rand(5, 15),
                    'location_name' => $wp['name'],
                    'external_id' => null,
                    'recorded_at' => $recordedAt,
                ];
            }
        }

        return $points;
    }

    private function buildSeaPoints(array $waypoints, Carbon $startTime, float $daysPerLeg): array
    {
        $points = [];
        foreach ($waypoints as $i => $wp) {
            $recordedAt = (clone $startTime)->addHours((int)($i * $daysPerLeg * 24));
            $points[] = [
                'source' => 'vessel_ais',
                'lat' => $wp['lat'] + (rand(-30, 30) / 1000),
                'lng' => $wp['lng'] + (rand(-30, 30) / 1000),
                'speed' => rand(140, 220) / 10, // 14–22 knots
                'heading' => $this->bearingToNext($waypoints, $i),
                'accuracy' => null,
                'location_name' => $wp['label'] ?? null,
                'external_id' => null,
                'recorded_at' => $recordedAt,
            ];
        }

        return $points;
    }

    private function bearingToNext(array $waypoints, int $index): int
    {
        if ($index >= count($waypoints) - 1) {
            return $index > 0 ? $this->bearingToNext($waypoints, $index - 1) : 0;
        }
        $lat1 = deg2rad($waypoints[$index]['lat']);
        $lat2 = deg2rad($waypoints[$index + 1]['lat']);
        $dLng = deg2rad($waypoints[$index + 1]['lng'] - $waypoints[$index]['lng']);

        $y = sin($dLng) * cos($lat2);
        $x = cos($lat1) * sin($lat2) - sin($lat1) * cos($lat2) * cos($dLng);

        return (int)((rad2deg(atan2($y, $x)) + 360) % 360);
    }
}
