<?php

namespace App\Services;

use App\Enums\TripSegmentTrackingSource;
use App\Enums\TripStatus;
use App\Enums\TripTransportationMode;
use App\Models\Trip;
use App\Models\TripTrackingPoint;
use Illuminate\Support\Facades\Log;

class TripTrackingService
{
    // Radius in meters within which we consider the vehicle to have "arrived"
    private const ARRIVAL_RADIUS_METERS = 5000; // 5 km — configurable per trip in future

    /**
     * Store a tracking point from any source.
     * Returns null if duplicate (idempotent on external_id).
     */
    public function record(Trip $trip, array $data): ?TripTrackingPoint
    {
        // Deduplication guard for sources with external IDs (e.g. FastTag seqNo)
        if (!empty($data['external_id'])) {
            $exists = TripTrackingPoint::where('trip_id', $trip->id)
                ->where('source', $data['source'])
                ->where('external_id', $data['external_id'])
                ->exists();

            if ($exists) return null;
        }

        $point = TripTrackingPoint::create([
            'trip_id' => $trip->id,
            'customer_id' => $trip->customer_id,
            'source' => $data['source'],
            'lat' => $data['lat'] ?? null,
            'lng' => $data['lng'] ?? null,
            'speed' => $data['speed'] ?? null,
            'heading' => $data['heading'] ?? null,
            'accuracy' => $data['accuracy'] ?? null,
            'location_name' => $data['location_name'] ?? null,
            'external_id' => $data['external_id'] ?? null,
            'recorded_at' => $data['recorded_at'] ?? now(),
            'raw_payload' => $data['raw_payload'] ?? null,
        ]);

        // Update denormalized last-known position on trip
        if ($point->lat && $point->lng) {
            $trip->updateQuietly([
                'last_known_lat' => $point->lat,
                'last_known_lng' => $point->lng,
                'last_known_source' => $data['source'],
                'last_tracked_at' => $point->recorded_at,
            ]);
        }

        // Attempt status auto-advancement based on geofence
        $this->checkGeofenceAdvancement($trip->fresh(), $point);

        return $point;
    }

    /**
     * Bulk-record multiple points (e.g. FastTag returning many toll hits at once).
     * Returns count of newly inserted points.
     */
    public function recordMany(Trip $trip, array $points): int
    {
        $inserted = 0;
        $lastPoint = null;

        foreach ($points as $data) {
            $point = $this->record($trip, $data);
            if ($point) {
                $inserted++;
                $lastPoint = $point;
            }
        }

        Log::info('TripTrackingService: bulk recorded', [
            'trip_id' => $trip->id,
            'attempted' => count($points),
            'inserted' => $inserted,
        ]);

        return $inserted;
    }

    /**
     * Check if the latest point means the vehicle has reached a geofenced destination.
     * Advances trip status accordingly.
     */
    private function checkGeofenceAdvancement(Trip $trip, TripTrackingPoint $point): void
    {
        if (!$point->lat || !$point->lng) return;

        $mode = $trip->transport_mode;

        // Road: dispatch → delivery location
        if ($mode === TripTransportationMode::Road && $trip->status === TripStatus::InTransit) {
            if ($trip->delivery_lat && $trip->delivery_lng) {
                $distance = $this->haversineMeters(
                    $point->lat, $point->lng,
                    (float)$trip->delivery_lat, (float)$trip->delivery_lng
                );

                if ($distance <= self::ARRIVAL_RADIUS_METERS) {
                    Log::info('TripTrackingService: vehicle arrived at delivery', [
                        'trip_id' => $trip->id,
                        'distance_m' => $distance,
                        'source' => $point->source,
                    ]);
                    // Status change is handled by TripService / update flow.
                    // Fire an event so other parts can react without tight coupling.
                    event(new \App\Events\VehicleArrivedAtDestination($trip, $point));
                }
            }
        }

        // Multimodal: road leg ends when vehicle reaches origin port
        if ($mode === TripTransportationMode::Multimodal && $trip->status === TripStatus::InTransit) {
            $originPort = \App\Models\CustomerPort::where('customer_id', $trip->customer_id)
                ->where('code', $trip->origin_port_code)
                ->first();

            if ($originPort?->lat && $originPort?->lng) {
                $distance = $this->haversineMeters(
                    $point->lat, $point->lng,
                    (float)$originPort->lat, (float)$originPort->lng
                );

                $radius = $originPort->geo_fence_radius ?? self::ARRIVAL_RADIUS_METERS;

                if ($distance <= $radius) {
                    Log::info('TripTrackingService: vehicle arrived at origin port', [
                        'trip_id' => $trip->id,
                        'port_code' => $trip->origin_port_code,
                        'distance_m' => $distance,
                    ]);
                    event(new \App\Events\VehicleArrivedAtDestination($trip, $point));
                }
            }
        }
    }

    /**
     * Haversine formula — distance in meters between two lat/lng points.
     */
    public function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000; // Earth radius in meters
        $φ1 = deg2rad($lat1);
        $φ2 = deg2rad($lat2);
        $Δφ = deg2rad($lat2 - $lat1);
        $Δλ = deg2rad($lng2 - $lng1);

        $a = sin($Δφ / 2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
