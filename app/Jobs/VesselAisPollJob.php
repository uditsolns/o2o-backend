<?php

namespace App\Jobs;

use App\Enums\TripSegmentTrackingSource;
use App\Enums\TripStatus;
use App\Enums\TripTransportationMode;
use App\Models\TripContainerTracking;
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
        $records = TripContainerTracking::with('trip')
            ->where('tracking_status', 'active')
            ->whereNotNull('current_vessel_imo')
            ->whereHas('trip', fn($q) => $q->whereIn('status', [
                TripStatus::OnVessel->value,
                TripStatus::InTransshipment->value,
            ])->whereIn('transport_mode', [
                TripTransportationMode::Sea->value,
                TripTransportationMode::Multimodal->value,
            ]))
            ->get();

        if ($records->isEmpty()) return;

        Log::info('VesselAisPollJob: polling', ['count' => $records->count()]);

        foreach ($records as $record) {
            try {
                $this->pollRecord($record, $aisService, $trackingService);
            } catch (\Throwable $e) {
                Log::error('VesselAisPollJob: record failed', [
                    'trip_id' => $record->trip_id,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function pollRecord(
        TripContainerTracking $record,
        VesselAisService      $aisService,
        TripTrackingService   $trackingService
    ): void
    {
        $position = $aisService->getVesselPosition($record->current_vessel_imo);

        if ($position === null) return;

        if ($position === 'rate_limited') {
            Log::warning('VesselAisPollJob: rate limited', ['trip_id' => $record->trip_id]);
            return;
        }

        $trip = $record->trip;

        // Record tracking point on the trip (unchanged behaviour)
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

        // Update container tracking record (vessel + AIS enrichment)
        $record->updateQuietly([
            'mt_vessel_ship_id' => $position['ship_id'],
            'last_vessel_position_at' => now(),
            'current_vessel_lat' => $position['lat'],
            'current_vessel_lng' => $position['lng'],
            'current_vessel_speed' => $position['speed_knots'],
            'current_vessel_heading' => $position['heading'],
            'current_vessel_position_at' => $position['timestamp'] ?? now(),
            'current_vessel_mmsi' => $position['mmsi'],
            'current_vessel_destination' => $position['destination'],
            'current_vessel_current_port' => $position['current_port'],
            'current_vessel_ais_eta' => $position['eta_calc'],
        ]);

        Log::info('VesselAisPollJob: position recorded', [
            'trip_id' => $record->trip_id,
            'vessel_imo' => $record->current_vessel_imo,
            'lat' => $position['lat'],
            'lng' => $position['lng'],
        ]);
    }
}
