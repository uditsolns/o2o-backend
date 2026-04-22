<?php

namespace App\Http\Controllers;

use App\Enums\TripStatus;
use App\Http\Requests\Trip\ChangeSealRequest;
use App\Http\Requests\Trip\ConfirmEpodRequest;
use App\Http\Requests\Trip\StartTripRequest;
use App\Http\Requests\Trip\StoreTripSegmentRequest;
use App\Http\Requests\Trip\StoreTripRequest;
use App\Http\Requests\Trip\UpdateTripRequest;
use App\Http\Requests\Trip\VesselInfoRequest;
use App\Http\Resources\TripContainerTrackingResource;
use App\Http\Resources\TripSegmentResource;
use App\Http\Resources\TripResource;
use App\Http\Resources\TripShipmentMilestoneResource;
use App\Models\Trip;
use App\Models\TripSegment;
use App\Services\TripService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TripController extends Controller
{
    public function __construct(private readonly TripService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Trip::class);

        $trips = QueryBuilder::for(Trip::class)
            ->allowedFilters([
                AllowedFilter::exact('customer_id'),
                AllowedFilter::exact('status'),
                AllowedFilter::exact('trip_type'),
                AllowedFilter::exact('transport_mode'),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('trip_ref', 'like', "%{$value}%")
                            ->orWhere('container_number', 'like', "%{$value}%")
                            ->orWhere('vehicle_number', 'like', "%{$value}%")
                            ->orWhere('bill_of_lading', 'like', "%{$value}%");
                    });
                }),
                AllowedFilter::callback('dispatch_date_from', fn($q, $v) => $q->whereDate('dispatch_date', '>=', $v)),
                AllowedFilter::callback('dispatch_date_to', fn($q, $v) => $q->whereDate('dispatch_date', '<=', $v)),
            ])
            ->allowedSorts(['trip_ref', 'status', 'dispatch_date', 'created_at'])
            ->allowedIncludes(['customer', 'seal', 'createdBy', 'documents', 'segments'])
            ->defaultSort('-created_at')
            ->paginate($request->query('per_page', 20))
            ->appends($request->query());

        return TripResource::collection($trips);
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        $this->authorize('create', Trip::class);

        $trip = $this->service->store($request->validated(), $request->user());

        return response()->json(new TripResource($trip->load('seal', 'createdBy', 'segments')), 201);
    }

    public function show(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        return response()->json(new TripResource(
            $trip->load('seal', 'createdBy', 'documents', 'segments', 'containerTracking')
        ));
    }

    public function update(UpdateTripRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $trip = $this->service->update($trip, $request->validated(), $request->user());

        return response()->json(new TripResource($trip->load('seal')));
    }

    public function start(StartTripRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $trip = $this->service->startTrip($trip, $request->validated(), $request->user());

        return response()->json(new TripResource($trip->load('seal')));
    }

    public function changeSeal(ChangeSealRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        abort_if($trip->status !== TripStatus::Draft, 422, 'Seal can only be changed while the trip is in Draft status.');

        $trip = $this->service->changeSeal($trip, $request->validated('seal_id'), $request->user());

        return response()->json(new TripResource($trip->load('seal')));
    }

    public function confirmEpod(ConfirmEpodRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('confirmDestination', $trip);

        $trip = $this->service->confirmEpod($trip, $request->validated(), $request->user());

        return response()->json(new TripResource($trip->load('seal')));
    }

    public function addVesselInfo(VesselInfoRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('addVesselInfo', $trip);

        $trip = $this->service->addVesselInfo($trip, $request->validated(), $request->user());

        return response()->json(new TripResource($trip));
    }

    public function timeline(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        return response()->json($trip->events()->orderBy('created_at')->get());
    }

    public function sealStatus(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        if (!$trip->seal) {
            return response()->json(['message' => 'No seal assigned to this trip.'], 404);
        }

        return response()->json([
            'seal_number' => $trip->seal->seal_number,
            'status' => $trip->seal->status,
            'sepio_status' => $trip->seal->sepio_status,
            'last_scan_at' => $trip->seal->last_scan_at,
            'latest_log' => $trip->seal->statusLogs()->orderByDesc('checked_at')->first(),
        ]);
    }

    // ── Trip Segments ─────────────────────────────────────────────────────────────

    public function segments(Trip $trip): AnonymousResourceCollection
    {
        $this->authorize('view', $trip);

        return TripSegmentResource::collection($trip->segments);
    }

    public function storeSegment(StoreTripSegmentRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        abort_if($trip->isLocked(), 403, 'Cannot modify segments of a completed trip.');

        $segment = TripSegment::create([
            ...$request->validated(),
            'trip_id' => $trip->id,
            'customer_id' => $trip->customer_id,
        ]);

        return response()->json(new TripSegmentResource($segment), 201);
    }

    public function updateSegment(StoreTripSegmentRequest $request, Trip $trip, TripSegment $segment): JsonResponse
    {
        $this->authorize('update', $trip);

        abort_if($segment->trip_id !== $trip->id, 404);
        abort_if($trip->isLocked(), 403, 'Cannot modify segments of a completed trip.');

        $segment->update($request->validated());

        return response()->json(new TripSegmentResource($segment->fresh()));
    }

    public function destroySegment(Trip $trip, TripSegment $segment): JsonResponse
    {
        $this->authorize('update', $trip);

        abort_if($segment->trip_id !== $trip->id, 404);
        abort_if($trip->isLocked(), 403, 'Cannot modify segments of a completed trip.');

        $segment->delete();

        return response()->json(['message' => 'Segment removed.']);
    }

    public function containerTracking(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $tracking = $trip->containerTracking;

        return response()->json(
            $tracking ? new TripContainerTrackingResource($tracking) : null
        );
    }

    public function shipmentMilestones(Trip $trip): AnonymousResourceCollection
    {
        $this->authorize('view', $trip);

        return TripShipmentMilestoneResource::collection(
            $trip->shipmentMilestones
        );
    }
}
