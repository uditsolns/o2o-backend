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

    /**
     * Transportation statuses where the container is NOT loaded on a moving vessel.
     * Polling the AIS API during these states serves no purpose.
     */
    private const PAUSED_STATUSES = [
        'booked',
        'not_arrived_at_port_of_loading',
        'waiting_at_port_of_loading',
        'waiting_at_a_transhipment_port',   // correct Kpler spelling
        'waiting_at_port_of_discharge',
        'left_the_port_of_discharge',
        'completed',
        'routing_data_inconclusive',
    ];

    public function handle(VesselAisService $aisService, TripTrackingService $trackingService): void
    {
        $records = TripContainerTracking::with('trip')
            ->where('tracking_status', 'active')
            ->whereNotNull('current_vessel_imo')
            // Skip statuses where vessel is not underway
            ->whereNotIn('transportation_status', self::PAUSED_STATUSES)
            ->whereHas('trip', fn($q) => $q
                ->where('status', TripStatus::Active->value)
                ->whereIn('transport_mode', [
                    TripTransportationMode::Sea->value,
                    TripTransportationMode::Multimodal->value,
                ])
            )
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

        // Deterministic external_id for deduplication:
        // same vessel + same AIS timestamp = same position event
        $externalId = ($position['mmsi'] ?? $record->current_vessel_imo)
            . '_'
            . ($position['timestamp'] ?? '');

        $trackingService->record($trip, [
            'source' => TripSegmentTrackingSource::VesselAis->value,
            'lat' => $position['lat'],
            'lng' => $position['lng'],
            'speed' => $position['speed_knots'],
            'heading' => $position['heading'],
            'location_name' => $position['destination'],
            'external_id' => $externalId,
            'recorded_at' => $position['timestamp'] ?? now(),
            'raw_payload' => $position,
        ]);

        // Merge AIS-enriched data into the current_vessel JSON snapshot
        $currentVessel = $record->current_vessel ?? [];
        $record->updateQuietly([
            'mt_vessel_ship_id' => $position['ship_id'],
            'last_vessel_position_at' => now(),
            'current_vessel' => array_merge($currentVessel, [
                'lat' => $position['lat'],
                'lng' => $position['lng'],
                'speed_knots' => $position['speed_knots'],
                'heading' => $position['heading'],
                'destination' => $position['destination'],
                'current_port' => $position['current_port'],
                'ais_eta' => $position['eta_calc'],
                'position_at' => ($position['timestamp'] ?? now()->toISOString()),
            ]),
        ]);

        Log::info('VesselAisPollJob: position recorded', [
            'trip_id' => $record->trip_id,
            'vessel_imo' => $record->current_vessel_imo,
            'lat' => $position['lat'],
            'lng' => $position['lng'],
        ]);
    }
}
