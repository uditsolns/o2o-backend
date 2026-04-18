<?php

namespace App\Jobs;

use App\Enums\TripSegmentTrackingSource;
use App\Enums\TripStatus;
use App\Enums\TripTransportationMode;
use App\Models\Trip;
use App\Services\TripTrackingService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class FastTagPollJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;

    private const FASTAG_API = 'https://elephantsoftwares.com/ulip-apis/public/api/ulip/fastag';

    public function handle(TripTrackingService $trackingService): void
    {
        // Only active road/multimodal trips with a vehicle number
        $trips = Trip::whereIn('status', [TripStatus::InTransit, TripStatus::AtPort])
            ->whereIn('transport_mode', [
                TripTransportationMode::Road->value,
                TripTransportationMode::Multimodal->value,
            ])
            ->whereNotNull('vehicle_number')
            ->get();

        if ($trips->isEmpty()) return;

        Log::info('FastTagPollJob: polling', ['trip_count' => $trips->count()]);

        foreach ($trips as $trip) {
            try {
                $this->pollTrip($trip, $trackingService);
            } catch (\Throwable $e) {
                Log::error('FastTagPollJob: trip poll failed', [
                    'trip_id' => $trip->id,
                    'vehicle_number' => $trip->vehicle_number,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }

    private function pollTrip(Trip $trip, TripTrackingService $trackingService): void
    {
        $response = Http::timeout(15)
            ->retry(2, 500)
            ->post(self::FASTAG_API, [
                'vehicle_number' => $trip->vehicle_number,
            ]);

        if ($response->failed()) {
            Log::warning('FastTagPollJob: API call failed', [
                'trip_id' => $trip->id,
                'vehicle_number' => $trip->vehicle_number,
                'status' => $response->status(),
            ]);
            return;
        }

        $txns = $response->json('data.vehicle.vehltxnList.txn', []);

        if (empty($txns)) return;

        // Filter to only transactions that occurred after the trip started
        // and after the last sync cursor
        $since = $trip->last_fastag_synced_at
            ?? $trip->trip_start_time
            ?? $trip->created_at;

        $points = [];
        $latestTime = null;

        foreach ($txns as $txn) {
            $recordedAt = Carbon::parse($txn['readerReadTime']);

            // Skip anything before trip start
            if ($recordedAt->lte($since)) continue;

            // Parse geocode "lat,lng"
            $lat = $lng = null;
            if (!empty($txn['tollPlazaGeocode'])) {
                $parts = array_map('trim', explode(',', $txn['tollPlazaGeocode']));
                if (count($parts) === 2 && is_numeric($parts[0]) && is_numeric($parts[1])) {
                    $lat = (float)$parts[0];
                    $lng = (float)$parts[1];
                }
            }

            $points[] = [
                'source' => TripSegmentTrackingSource::FastTag->value,
                'lat' => $lat,
                'lng' => $lng,
                'location_name' => $txn['tollPlazaName'] ?? null,
                'external_id' => $txn['seqNo'],
                'recorded_at' => $recordedAt,
                'raw_payload' => $txn,
            ];

            if (!$latestTime || $recordedAt->gt($latestTime)) {
                $latestTime = $recordedAt;
            }
        }

        if (empty($points)) return;

        // Sort ascending by time before recording
        usort($points, fn($a, $b) => $a['recorded_at'] <=> $b['recorded_at']);

        $inserted = $trackingService->recordMany($trip, $points);

        // Advance cursor so next poll only processes new data
        if ($latestTime && $inserted > 0) {
            $trip->updateQuietly(['last_fastag_synced_at' => $latestTime]);
        }

        Log::info('FastTagPollJob: points recorded', [
            'trip_id' => $trip->id,
            'vehicle_number' => $trip->vehicle_number,
            'new_points' => $inserted,
        ]);
    }
}
