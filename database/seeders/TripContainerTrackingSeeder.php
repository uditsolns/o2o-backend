<?php

namespace Database\Seeders;

use App\Enums\CustomerOnboardingStatus;
use App\Enums\TripStatus;
use App\Enums\TripTransportationMode;
use App\Models\Customer;
use App\Models\Trip;
use App\Models\TripContainerTracking;
use Illuminate\Database\Seeder;

/**
 * Seeds trip_container_tracking rows for sea/multimodal trips.
 *
 * The 2026_05_20 schema redesign collapsed dozens of flat columns into JSON snapshots:
 *   - pol, pod, current_vessel   (Port of Loading / Discharge / current vessel)
 *   - carrier, container_specs   (carrier info, container ISO/type/size)
 *   - insights, eta_history, rollover_history, transshipment_ports
 *   - pol_change_history, pod_change_history, raw_shipment_snapshot
 *
 * Plus the new (post-redesign) columns:
 *   - transportation_status, transportation_status_updated_at, is_routing_inconclusive
 *   - mt_shipment_id (unique), mt_vessel_ship_id
 *   - current_vessel_imo, last_vessel_position_at
 *   - tracking_status (enum)
 */
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
        $ts = fn(string $hours) => now()->subHours((int) $hours);

        // Common shared JSON for the pol/pod snapshots
        $polSnapshot = fn() => [
            'unlocode' => $this->toUnlocode($trip->origin_port_code),
            'name' => $trip->origin_port_name,
            'lat' => $this->portLat($trip->origin_port_code),
            'lng' => $this->portLng($trip->origin_port_code),
            'country' => $this->portCountry($trip->origin_port_code),
            'etd' => optional($trip->etd)->toIso8601String(),
        ];

        $podSnapshot = fn() => [
            'unlocode' => $this->toUnlocode($trip->destination_port_code),
            'name' => $trip->destination_port_name,
            'lat' => $this->portLat($trip->destination_port_code),
            'lng' => $this->portLng($trip->destination_port_code),
            'country' => $this->portCountry($trip->destination_port_code),
            'eta' => optional($trip->eta)->toIso8601String(),
        ];

        $carrier = [
            'scac' => $trip->carrier_scac ?? 'MSCU',
            'name' => $this->carrierName($trip->carrier_scac),
        ];

        $containerSpecs = [
            'iso_code' => $trip->container_type === '40HC' ? '45G1' : ($trip->container_type === '40GP' ? '42G1' : '22G1'),
            'type' => $trip->container_type === '40HC' ? 'high_cube' : 'standard',
            'size' => str_starts_with($trip->container_type, '40') ? 40 : 20,
        ];

        return match ($trip->status) {

            // ── Draft-like "pre-departure" active trips ────────────────────
            TripStatus::Active => [
                'mt_tracking_request_id' => $this->mtTrackingRequestId($trip),
                'mt_shipment_id' => null,
                'tracking_status' => 'pending',
                'transportation_status' => 'in_transit',
                'transportation_status_updated_at' => $ts('2'),
                'is_routing_inconclusive' => false,
                'pol' => $polSnapshot(),
                'pod' => $podSnapshot(),
                'carrier' => $carrier,
                'container_specs' => $containerSpecs,
                'insights' => null,
                'last_synced_at' => $ts('2'),
            ],

            TripStatus::OutForDelivery => [
                'mt_tracking_request_id' => $this->mtTrackingRequestId($trip),
                'mt_shipment_id' => $this->mtShipmentId($trip),
                'mt_vessel_ship_id' => $this->mtVesselShipId($trip),
                'tracking_status' => 'active',
                'transportation_status' => 'in_transit',
                'transportation_status_updated_at' => $ts('4'),
                'is_routing_inconclusive' => false,
                'pol' => $polSnapshot(),
                'pod' => $podSnapshot(),
                'carrier' => $carrier,
                'container_specs' => $containerSpecs,
                'insights' => [
                    'arrival_delay_days' => 0,
                    'initial_carrier_eta' => optional($trip->eta)->toIso8601String(),
                    'has_rollover' => false,
                ],
                'eta_history' => [
                    ['eta' => optional($trip->eta)->toIso8601String(), 'recorded_at' => $ts('72')],
                ],
                'current_vessel_imo' => null,
                'last_vessel_position_at' => null,
                'last_synced_at' => $ts('1'),
            ],

            TripStatus::Delivered => [
                'mt_tracking_request_id' => $this->mtTrackingRequestId($trip),
                'mt_shipment_id' => $this->mtShipmentId($trip),
                'mt_vessel_ship_id' => $this->mtVesselShipId($trip),
                'tracking_status' => 'active',
                'transportation_status' => 'arrived',
                'transportation_status_updated_at' => $ts('6'),
                'is_routing_inconclusive' => false,
                'pol' => $polSnapshot(),
                'pod' => $podSnapshot(),
                'carrier' => $carrier,
                'container_specs' => $containerSpecs,
                'insights' => [
                    'arrival_delay_days' => 1,
                    'initial_carrier_eta' => optional($trip->eta)->toIso8601String(),
                    'has_rollover' => false,
                ],
                'eta_history' => [
                    ['eta' => optional($trip->eta)->toIso8601String(), 'recorded_at' => $ts('72')],
                ],
                'current_vessel_imo' => null,
                'last_vessel_position_at' => null,
                'last_synced_at' => $ts('6'),
            ],

            TripStatus::Completed => [
                'mt_tracking_request_id' => $this->mtTrackingRequestId($trip),
                'mt_shipment_id' => $this->mtShipmentId($trip),
                'mt_vessel_ship_id' => $this->mtVesselShipId($trip),
                'tracking_status' => 'active',
                'transportation_status' => 'delivered',
                'transportation_status_updated_at' => $ts('48'),
                'is_routing_inconclusive' => false,
                'pol' => $polSnapshot(),
                'pod' => $podSnapshot(),
                'carrier' => $carrier,
                'container_specs' => $containerSpecs,
                'insights' => [
                    'arrival_delay_days' => 0,
                    'initial_carrier_eta' => optional($trip->eta)->toIso8601String(),
                    'has_rollover' => false,
                ],
                'eta_history' => [
                    ['eta' => optional($trip->eta)->toIso8601String(), 'recorded_at' => $ts('240')],
                ],
                'last_synced_at' => $ts('48'),
            ],

            default => [
                'tracking_status' => 'not_registered',
            ],
        };
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function toUnlocode(?string $code): ?string
    {
        if (!$code) return null;
        return strtoupper($code);
    }

    private function portLat(?string $code): ?float
    {
        return match ($code) {
            'INNSA' => 18.9488, 'INMUN' => 22.8381, 'INMAA' => 13.0836,
            'INVTZ' => 17.6868, 'INCOK' => 9.9312, 'INTDL' => 28.5011,
            'AEJEA' => 24.9857, 'NLRTM' => 51.9244, 'SGSIN' => 1.2566,
            'CNSHA' => 30.6218, 'DEHAM' => 53.5439, default => null,
        };
    }

    private function portLng(?string $code): ?float
    {
        return match ($code) {
            'INNSA' => 72.9511, 'INMUN' => 69.7032, 'INMAA' => 80.2969,
            'INVTZ' => 83.2185, 'INCOK' => 76.2673, 'INTDL' => 77.2877,
            'AEJEA' => 55.0272, 'NLRTM' => 4.4777, 'SGSIN' => 103.8198,
            'CNSHA' => 122.0580, 'DEHAM' => 9.9569, default => null,
        };
    }

    private function portCountry(?string $code): ?string
    {
        return match (true) {
            $code && str_starts_with($code, 'IN') => 'India',
            $code && str_starts_with($code, 'AE') => 'UAE',
            $code && str_starts_with($code, 'NL') => 'Netherlands',
            $code && str_starts_with($code, 'SG') => 'Singapore',
            $code && str_starts_with($code, 'CN') => 'China',
            $code && str_starts_with($code, 'DE') => 'Germany',
            default => null,
        };
    }

    private function carrierName(?string $scac): ?string
    {
        return match ($scac) {
            'MSCU' => 'Mediterranean Shipping Company',
            'HLCU' => 'Hapag-Lloyd',
            'CMDU' => 'CMA CGM',
            'COSU' => 'COSCO Shipping',
            'OOLU' => 'OOCL',
            'EGLV' => 'Evergreen Line',
            'CSCL' => 'China Shipping',
            'APLU' => 'APL',
            default => null,
        };
    }

    /**
     * Generate a synthetic MarineTraffic vessel ship ID from the trip_ref.
     * In production this comes from the Kpler/MarineTraffic shipment record.
     */
    private function mtVesselShipId(Trip $trip): string
    {
        $hash = substr(md5($trip->trip_ref . $trip->carrier_scac), 0, 10);
        return 'MTSHIP-' . strtoupper($hash);
    }

    /**
     * Kpler-style 26-character base32-ish tracking request id, deterministic
     * per (customer_id, trip_id). Mirrors the production id shape so URLs
     * like /tracking/{requestId} look right during local dev.
     */
    private function mtTrackingRequestId(Trip $trip): string
    {
        $hash = hash('sha256', $trip->customer_id . ':' . $trip->id . ':req');
        return $this->kplerStyleId($hash);
    }

    /**
     * Kpler-style 26-character shipment id, deterministic per (trip_id).
     */
    private function mtShipmentId(Trip $trip): string
    {
        $hash = hash('sha256', $trip->id . ':shipment');
        return $this->kplerStyleId($hash);
    }

    /**
     * Map a hex SHA into a 26-char [a-z0-9] id — same shape Kpler returns.
     */
    private function kplerStyleId(string $hash): string
    {
        $alphabet = 'abcdefghijklmnopqrstuvwxyz0123456789';
        $bytes = substr(hash('sha256', $hash), 0, 26);
        $id = '';
        for ($i = 0, $n = strlen($bytes); $i < $n; $i++) {
            $id .= $alphabet[hexdec(substr($bytes, $i, 2)) % strlen($alphabet)];
        }
        return $id;
    }
}
