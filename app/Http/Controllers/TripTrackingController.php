<?php

namespace App\Http\Controllers;

use App\Enums\TripSegmentTrackingSource;
use App\Enums\TripStatus;
use App\Http\Requests\Trip\PushLocationRequest;
use App\Http\Resources\TripTrackingPointResource;
use App\Models\Trip;
use App\Services\TripTrackingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TripTrackingController extends Controller
{
    public function __construct(private readonly TripTrackingService $trackingService)
    {
    }

    /**
     * GET /trips/{trip}/tracking
     *
     * Default (detail=summary): downsampled Google-encoded polyline + summary stats.
     * Designed for both mobile and web — ~3 KB for a 14 000-point trip.
     *
     * ?detail=raw: paginated raw points via cursor pagination (forensic/timeline).
     *
     * Query params:
     *   detail   summary|raw  (default: summary)
     *   cursor               base64 cursor for raw pagination
     *   limit                page size for raw (default 1000, max 2000)
     *   source               filter by source (e.g. vessel_ais)
     *   from, to             time window (ISO 8601)
     *   zoom                 map zoom 1-20 — adaptive polyline density
     */
    public function history(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        if ($request->query('detail') === 'raw') {
            return $this->historyRaw($request, $trip);
        }

        return $this->historySummary($request, $trip);
    }

    private function historySummary(Request $request, Trip $trip): JsonResponse
    {
        $zoom   = (int) $request->query('zoom', 10);
        $target = match (true) {
            $zoom <= 4  => 100,
            $zoom <= 8  => 250,
            $zoom <= 12 => 500,
            default     => 1000,
        };

        $summary  = $this->trackingService->summarize($trip);
        $polyData = $this->trackingService->decimate($trip, $target);

        return response()->json([
            'summary'            => $summary,
            'polyline'           => $polyData['polyline'],
            'timestamps'         => $polyData['timestamps'],
            'points_in_polyline' => $polyData['points_in_polyline'],
            'bbox'               => $polyData['bbox'],
            'segments'           => $polyData['segments'],
            'data'               => [],   // empty for map view; backward-compat
        ]);
    }

    private function historyRaw(Request $request, Trip $trip): JsonResponse
    {
        $limit    = min((int) $request->query('limit', 1000), 2000);
        $summary  = $this->trackingService->summarize($trip);
        $paginated = $trip->trackingPoints()
            ->when($request->query('source'), fn($q, $v) => $q->where('source', $v))
            ->when($request->query('from'),   fn($q, $v) => $q->where('recorded_at', '>=', $v))
            ->when($request->query('to'),      fn($q, $v) => $q->where('recorded_at', '<=', $v))
            ->orderBy('recorded_at')
            ->cursorPaginate($limit);

        $polyData = $this->trackingService->decimate($trip, 100);

        return response()->json([
            'summary'            => $summary,
            'polyline'           => $polyData['polyline'],
            'timestamps'         => $polyData['timestamps'],
            'points_in_polyline' => $polyData['points_in_polyline'],
            'bbox'               => $polyData['bbox'],
            'segments'           => $polyData['segments'],
            'data'               => TripTrackingPointResource::collection($paginated->items()),
            'links'              => [
                'next' => $paginated->nextPageUrl(),
                'prev' => $paginated->previousPageUrl(),
            ],
            'meta'               => [
                'has_more'    => $paginated->hasMorePages(),
                'per_page'    => $paginated->perPage(),
                'total_points' => $summary['total_points'],
            ],
        ]);
    }

    /**
     * POST /trips/{trip}/location
     * Driver mobile pushes a location update.
     * Auth: trip tracking_token (query param) OR Sanctum auth.
     */
    public function pushLocation(PushLocationRequest $request, Trip $trip): JsonResponse
    {
        $this->authorizeTrackingPush($request, $trip);

        abort_if(
            $trip->status !== TripStatus::Active,
            422,
            'Location updates are only accepted for active trips.'
        );

        $point = $this->trackingService->record($trip, [
            'source' => TripSegmentTrackingSource::DriverMobile->value,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'speed' => $request->speed,
            'heading' => $request->heading,
            'accuracy' => $request->accuracy,
            'recorded_at' => $request->recorded_at ?? now(),
            'raw_payload' => $request->only(['lat', 'lng', 'speed', 'heading', 'accuracy', 'recorded_at']),
        ]);

        return response()->json([
            'message' => 'Location recorded.',
            'point' => $point ? new TripTrackingPointResource($point) : null,
        ], $point ? 201 : 200);
    }

    /**
     * GET /trips/{trip}/tracking/latest
     * Return the latest known position for a trip.
     */
    public function latest(Request $request, Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $point = $trip->trackingPoints()->orderByDesc('recorded_at')->first();

        return response()->json([
            'last_known_lat' => $trip->last_known_lat,
            'last_known_lng' => $trip->last_known_lng,
            'last_known_source' => $trip->last_known_source,
            'last_tracked_at' => $trip->last_tracked_at,
            'latest_point' => $point ? new TripTrackingPointResource($point) : null,
        ]);
    }

    /**
     * POST /tracking/driver-mobile (no Sanctum, token-only route)
     * Driver pushes location using just the trip tracking_token.
     * The trip is resolved from the token.
     */
    public function driverPush(PushLocationRequest $request): JsonResponse
    {
        $token = $request->input('tracking_token') ?? $request->header('X-Tracking-Token');

        abort_if(!$token, 401, 'Tracking token is required.');

        $trip = Trip::where('tracking_token', $token)->first();

        abort_if(!$trip, 401, 'Invalid tracking token.');

        abort_if(
            $trip->status !== TripStatus::Active,
            422,
            'Location updates are only accepted for active trips.'
        );

        $point = $this->trackingService->record($trip, [
            'source' => TripSegmentTrackingSource::DriverMobile->value,
            'lat' => $request->lat,
            'lng' => $request->lng,
            'speed' => $request->speed,
            'heading' => $request->heading,
            'accuracy' => $request->accuracy,
            'recorded_at' => $request->recorded_at ?? now(),
            'raw_payload' => $request->only(['lat', 'lng', 'speed', 'heading', 'accuracy', 'recorded_at']),
        ]);

        return response()->json(['message' => 'Location recorded.'], $point ? 201 : 200);
    }

    private function authorizeTrackingPush(Request $request, Trip $trip): void
    {
        $token = $request->header('X-Tracking-Token') ?? $request->query('tracking_token');

        if ($token) {
            abort_if($trip->tracking_token !== $token, 401, 'Invalid tracking token.');
            return;
        }

        // Fall back to Sanctum auth check
        $this->authorize('update', $trip);
    }
}
