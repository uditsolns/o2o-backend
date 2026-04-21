<?php

namespace App\Http\Controllers\Inspectors;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class MarineTrafficInspectorController extends Controller
{
    private function guardProd(): void
    {
        abort_if(app()->isProduction(), 404);
    }

    /**
     * Proxy a request to the Container Tracking API (Kpler).
     * Auth header: X-Container-API-Key (injected server-side from config).
     */
    public function proxyContainer(Request $request): JsonResponse
    {
        $this->guardProd();

        $path = $request->input('path');
        $method = strtolower($request->input('method', 'get'));
        $params = $request->input('params', []);

        abort_if(!$path, 400, 'path is required.');

        $baseUrl = rtrim(config('marinetraffic.container_base_url'), '/');
        $url = $baseUrl . '/' . ltrim($path, '/');

        $start = microtime(true);

        try {
            $http = Http::withHeader('X-Container-API-Key', config('marinetraffic.container_api_key'))
                ->accept('application/json')
                ->timeout(30);

            $response = $method === 'post'
                ? $http->post($url, $params)
                : $http->get($url, $params);

            return response()->json([
                'status' => $response->status(),
                'body' => $response->json() ?? ['_raw' => $response->body()],
                'elapsed_ms' => round((microtime(true) - $start) * 1000),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 0,
                'body' => ['error' => $e->getMessage()],
                'elapsed_ms' => round((microtime(true) - $start) * 1000),
            ]);
        }
    }

    /**
     * Proxy a request to the AIS Vessel API (MarineTraffic services).
     * API key is appended to the URL path server-side.
     */
    public function proxyVessel(Request $request): JsonResponse
    {
        $this->guardProd();

        $endpoint = $request->input('endpoint', 'exportvessel'); // exportvessel | exportvessels
        $params = $request->input('params', []);

        $apiKey = config('marinetraffic.vessel_api_key');
        $baseUrl = rtrim(config('marinetraffic.vessel_base_url'), '/');
        $url = "{$baseUrl}/{$endpoint}/{$apiKey}";

        // Always force jsono protocol
        $params['protocol'] = 'jsono';

        $start = microtime(true);

        try {
            $response = Http::accept('application/json')
                ->timeout(30)
                ->get($url, $params);

            return response()->json([
                'status' => $response->status(),
                'body' => $response->json() ?? ['_raw' => $response->body()],
                'elapsed_ms' => round((microtime(true) - $start) * 1000),
            ]);
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 0,
                'body' => ['error' => $e->getMessage()],
                'elapsed_ms' => round((microtime(true) - $start) * 1000),
            ]);
        }
    }

    /**
     * Return active container tracking records for context in the inspector.
     */
    public function activeTrackings(Request $request): JsonResponse
    {
        $this->guardProd();

        $trackings = \App\Models\TripContainerTracking::with('trip:id,trip_ref,container_number,carrier_scac')
            ->whereIn('tracking_status', ['active', 'pending'])
            ->latest()
            ->limit(50)
            ->get([
                'id', 'trip_id', 'container_number', 'carrier_scac',
                'mt_tracking_request_id', 'mt_shipment_id', 'tracking_status',
                'transportation_status', 'last_synced_at',
            ]);

        return response()->json($trackings);
    }

    /**
     * Return trips that are on-vessel / in-transshipment for AIS polling context.
     */
    public function activeVesselTrips(Request $request): JsonResponse
    {
        $this->guardProd();

        $trips = \App\Models\Trip::whereIn('status', ['on_vessel', 'in_transshipment'])
            ->whereNotNull('vessel_imo_number')
            ->latest()
            ->limit(50)
            ->get([
                'id', 'trip_ref', 'vessel_name', 'vessel_imo_number',
                'mt_vessel_ship_id', 'container_number', 'carrier_scac',
                'status', 'eta', 'last_vessel_position_at',
            ]);

        return response()->json($trips);
    }
}
