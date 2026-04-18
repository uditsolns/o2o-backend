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
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TripTrackingController extends Controller
{
    public function __construct(private readonly TripTrackingService $trackingService)
    {
    }

    /**
     * GET /trips/{trip}/tracking
     * Return paginated tracking history for a trip.
     */
    public function history(Request $request, Trip $trip): AnonymousResourceCollection
    {
        $this->authorize('view', $trip);

        $points = $trip->trackingPoints()
            ->when($request->query('source'), fn($q, $v) => $q->where('source', $v))
            ->when($request->query('from'), fn($q, $v) => $q->where('recorded_at', '>=', $v))
            ->when($request->query('to'), fn($q, $v) => $q->where('recorded_at', '<=', $v))
            ->orderByDesc('recorded_at')
            ->paginate($request->query('per_page', 100));

        return TripTrackingPointResource::collection($points);
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
            !in_array($trip->status, [TripStatus::InTransit, TripStatus::AtPort], true),
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
            !in_array($trip->status, [TripStatus::InTransit, TripStatus::AtPort], true),
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
