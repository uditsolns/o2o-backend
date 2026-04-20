<?php

namespace App\Services\MarineTraffic;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class VesselAisService
{
    public function getVesselPosition(string $imo): array|string|null
    {
        $response = Http::timeout(15)
            ->retry(2, 1000)
            ->get(config('marinetraffic.vessel_base_url') . '/exportvessel/' . config('marinetraffic.vessel_api_key'), [
                'v' => 6,
                'imo' => $imo,
                'timespan' => config('marinetraffic.vessel_timespan'),
                'protocol' => 'jsono',
            ]);

        if ($response->status() === 429) {
            return 'rate_limited';
        }

        if ($response->failed()) {
            Log::warning('VesselAisService: position fetch failed', [
                'imo' => $imo,
                'status' => $response->status(),
            ]);
            return null;
        }

        $data = $response->json();
        $vessel = is_array($data) ? ($data[0] ?? null) : null;

        if (!$vessel || empty($vessel['LAT'])) return null;

        return [
            'ship_id' => $vessel['SHIP_ID'] ?? null,
            'mmsi' => $vessel['MMSI'] ?? null,
            'imo' => $vessel['IMO'] ?? null,
            'name' => $vessel['SHIPNAME'] ?? null,
            'lat' => (float)$vessel['LAT'],
            'lng' => (float)$vessel['LON'],
            'speed_knots' => isset($vessel['SPEED']) ? round((int)$vessel['SPEED'] / 10, 1) : null,
            'heading' => isset($vessel['HEADING']) && !in_array((int)$vessel['HEADING'], [-1, 511])
                ? (int)$vessel['HEADING'] : null,
            'course' => isset($vessel['COURSE']) ? (float)$vessel['COURSE'] : null,
            'ais_status' => isset($vessel['STATUS']) ? (int)$vessel['STATUS'] : null,
            'destination' => $vessel['DESTINATION'] ?? null,
            'eta' => !empty($vessel['ETA']) ? $vessel['ETA'] : null,
            'timestamp' => $vessel['TIMESTAMP'] ?? null,
            'dsrc' => $vessel['DSRC'] ?? null,
        ];
    }
}
