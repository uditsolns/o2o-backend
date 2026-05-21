<?php

namespace App\Services;

use App\Enums\TripSegmentTrackingSource;
use App\Enums\TripStatus;
use App\Enums\TripTransportationMode;
use App\Events\VehicleArrivedAtDestination;
use App\Models\Trip;
use App\Models\TripTrackingPoint;
use Illuminate\Support\Facades\Log;

class TripTrackingService
{
    private const ARRIVAL_RADIUS_METERS = 5000;

    /**
     * Minimum distance (meters) before a new point is stored.
     * Prevents accumulating duplicate positions during slow steaming or at anchor.
     */
    private const MIN_DISTANCE_METERS = [
        'vessel_ais' => 500,
        'driver_mobile' => 100,
        'fast_tag' => 0,    // FastTag uses external_id dedup, not distance
        'default' => 100,
    ];

    /**
     * Maximum time (seconds) between stored points regardless of distance.
     * Ensures we always record at least one point per interval even if stationary,
     * EXCEPT for vessel_ais where stationary = anchored and we explicitly skip.
     */
    private const MAX_INTERVAL_SECONDS = [
        'vessel_ais' => null,   // null = never force-insert on time alone for vessels
        'driver_mobile' => 300,    // 5 minutes
        'fast_tag' => 0,
        'default' => 300,
    ];

    public function record(Trip $trip, array $data): ?TripTrackingPoint
    {
        // Deduplication guard for sources with natural external IDs (FastTag seqNo, AIS timestamp+mmsi)
        if (!empty($data['external_id'])) {
            $exists = TripTrackingPoint::where('trip_id', $trip->id)
                ->where('source', $data['source'])
                ->where('external_id', $data['external_id'])
                ->exists();

            if ($exists) return null;
        }

        // Movement threshold guard — skip if too close to last point and not enough time passed
        if ($data['lat'] && $data['lng']) {
            if ($this->shouldSkipPoint($trip, $data)) {
                return null;
            }
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

        if ($point->lat && $point->lng) {
            $trip->updateQuietly([
                'last_known_lat' => $point->lat,
                'last_known_lng' => $point->lng,
                'last_known_source' => $data['source'],
                'last_tracked_at' => $point->recorded_at,
            ]);
        }

        $this->checkGeofenceAdvancement($trip->fresh(), $point);

        return $point;
    }

    /**
     * Determine whether a new point should be skipped based on movement threshold.
     * Returns true (skip) if the point is too close to the last stored point
     * AND not enough time has elapsed.
     */
    private function shouldSkipPoint(Trip $trip, array $data): bool
    {
        $source = $data['source'];

        $minDistance = self::MIN_DISTANCE_METERS[$source] ?? self::MIN_DISTANCE_METERS['default'];
        $maxInterval = self::MAX_INTERVAL_SECONDS[$source] ?? self::MAX_INTERVAL_SECONDS['default'];

        // Sources with no distance threshold (FastTag uses external_id, skip threshold logic)
        if ($minDistance === 0) {
            return false;
        }

        $lastPoint = TripTrackingPoint::where('trip_id', $trip->id)
            ->where('source', $source)
            ->whereNotNull('lat')
            ->whereNotNull('lng')
            ->orderByDesc('recorded_at')
            ->first(['lat', 'lng', 'recorded_at']);

        if (!$lastPoint) {
            return false; // No previous point, always insert
        }

        $distance = $this->haversineMeters(
            (float)$data['lat'],
            (float)$data['lng'],
            (float)$lastPoint->lat,
            (float)$lastPoint->lng
        );

        $recordedAt = $data['recorded_at'] ?? now();
        $secondsSinceLast = $lastPoint->recorded_at->diffInSeconds($recordedAt);

        // Always insert if moved far enough
        if ($distance >= $minDistance) {
            return false;
        }

        // For vessel AIS: never force-insert on time alone (vessel at anchor = no point needed)
        if ($maxInterval === null) {
            return true; // skip — didn't move far enough and time-forcing is disabled
        }

        // For road: force-insert if max interval exceeded (heartbeat while stationary)
        if ($secondsSinceLast >= $maxInterval) {
            return false;
        }

        return true; // skip
    }

    public function recordMany(Trip $trip, array $points): int
    {
        $inserted = 0;

        foreach ($points as $data) {
            $point = $this->record($trip, $data);
            if ($point) {
                $inserted++;
            }
        }

        Log::info('TripTrackingService: bulk recorded', [
            'trip_id' => $trip->id,
            'attempted' => count($points),
            'inserted' => $inserted,
        ]);

        return $inserted;
    }

    private function checkGeofenceAdvancement(Trip $trip, TripTrackingPoint $point): void
    {
        if (!$point->lat || !$point->lng) return;

        $mode = $trip->transport_mode;

        if ($mode === TripTransportationMode::Road && $trip->status === TripStatus::Active) {
            if ($trip->delivery_lat && $trip->delivery_lng) {
                $distance = $this->haversineMeters(
                    $point->lat, $point->lng,
                    (float)$trip->delivery_lat, (float)$trip->delivery_lng
                );

                if ($distance <= self::ARRIVAL_RADIUS_METERS) {
                    event(new VehicleArrivedAtDestination($trip, $point));
                }
            }
        }

        if ($mode === TripTransportationMode::Multimodal && $trip->status === TripStatus::Active) {
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
                    event(new VehicleArrivedAtDestination($trip, $point));
                }
            }
        }
    }

    public function haversineMeters(float $lat1, float $lng1, float $lat2, float $lng2): float
    {
        $R = 6371000;
        $φ1 = deg2rad($lat1);
        $φ2 = deg2rad($lat2);
        $Δφ = deg2rad($lat2 - $lat1);
        $Δλ = deg2rad($lng2 - $lng1);

        $a = sin($Δφ / 2) ** 2 + cos($φ1) * cos($φ2) * sin($Δλ / 2) ** 2;

        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
