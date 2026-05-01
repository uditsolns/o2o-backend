<?php

namespace App\Jobs;

use App\Enums\TripSegmentTrackingSource;
use App\Enums\TripStatus;
use App\Enums\TripTransportationMode;
use App\Models\Trip;
use App\Services\MarineTraffic\VesselAisService;
use App\Services\TripTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class VesselAisPollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    public function handle(VesselAisService $aisService, TripTrackingService $trackingService): void
    {
//        $trips = Trip::whereIn('status', [
//            TripStatus::OnVessel,
//            TripStatus::InTransshipment,
//        ])
//            ->whereIn('transport_mode', [
//                TripTransportationMode::Sea,
//                TripTransportationMode::Multimodal,
//            ])
//            ->whereNotNull('vessel_imo_number')
//            ->get();

        $trips = Trip::whereNotNull('vessel_imo_number')
            ->whereIn('id', [20])
            ->get();

        if ($trips->isEmpty()) return;

        Log::info('VesselAisPollJob: polling', ['count' => $trips->count()]);

        foreach ($trips as $trip) {
            try {
                $this->pollTrip($trip, $aisService, $trackingService);
            } catch (\Throwable $e) {
                Log::error('VesselAisPollJob: trip failed', [
                    'trip_id' => $trip->id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function pollTrip(Trip $trip, VesselAisService $aisService, TripTrackingService $trackingService): void
    {
        $position = $aisService->getVesselPosition($trip->vessel_imo_number);

        if ($position === null) return;

        // Rate limit hit — back off, don't update last_vessel_position_at
        if ($position === 'rate_limited') {
            Log::warning('VesselAisPollJob: rate limited by MarineTraffic', ['trip_id' => $trip->id]);
            return;
        }

        if (!empty($position['ship_id']) && $trip->mt_vessel_ship_id !== $position['ship_id']) {
            $trip->updateQuietly(['mt_vessel_ship_id' => $position['ship_id']]);
        }

        $trackingService->record($trip, [
            'source' => TripSegmentTrackingSource::VesselAis->value,
            'lat' => $position['lat'],
            'lng' => $position['lng'],
            'speed' => $position['speed_knots'],
            'heading' => $position['heading'],
            'location_name' => $position['destination'],
            'recorded_at' => $position['timestamp'] ?? now(),
            'raw_payload' => $position,
        ]);

        $trip->updateQuietly(['last_vessel_position_at' => now()]);

        Log::info('VesselAisPollJob: position recorded', [
            'trip_id' => $trip->id,
            'vessel_imo' => $trip->vessel_imo_number,
            'lat' => $position['lat'],
            'lng' => $position['lng'],
        ]);
    }
}
