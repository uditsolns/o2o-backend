<?php

namespace App\Http\Controllers;

use App\Http\Requests\Trip\ConfirmDestinationRequest;
use App\Http\Requests\Trip\StoreTripRequest;
use App\Http\Requests\Trip\UpdateTripRequest;
use App\Http\Requests\Trip\VesselInfoRequest;
use App\Http\Resources\TripResource;
use App\Models\Trip;
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
            ->allowedIncludes(['seal', 'route', 'createdBy', 'documents'])
            ->defaultSort('-created_at')
            ->paginate(20)
            ->appends($request->query());

        return TripResource::collection($trips);
    }

    public function store(StoreTripRequest $request): JsonResponse
    {
        $this->authorize('create', Trip::class);

        $trip = $this->service->store($request->validated(), $request->user());

        return response()->json(new TripResource(
            $trip->load('seal', 'createdBy')
        ), 201);
    }

    public function show(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        return response()->json(new TripResource(
            $trip->load('seal', 'route', 'createdBy', 'documents')
        ));
    }

    public function update(UpdateTripRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('update', $trip);

        $trip = $this->service->update($trip, $request->validated(), $request->user());

        return response()->json(new TripResource($trip->load('seal')));
    }

    public function addVesselInfo(VesselInfoRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('addVesselInfo', $trip);

        $trip = $this->service->addVesselInfo($trip, $request->validated(), $request->user());

        return response()->json(new TripResource($trip));
    }

    public function confirmDestination(ConfirmDestinationRequest $request, Trip $trip): JsonResponse
    {
        $this->authorize('confirmDestination', $trip);

        $trip = $this->service->confirmDestination($trip, $request->validated(), $request->user());

        return response()->json(new TripResource($trip->load('seal')));
    }

    public function timeline(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        $events = $trip->events()->orderBy('created_at')->get();

        return response()->json($events);
    }

    public function sealStatus(Trip $trip): JsonResponse
    {
        $this->authorize('view', $trip);

        if (!$trip->seal) {
            return response()->json(['message' => 'No seal assigned to this trip.'], 404);
        }

        $latestLog = $trip->seal->statusLogs()
            ->orderByDesc('checked_at')
            ->first();

        return response()->json([
            'seal_number' => $trip->seal->seal_number,
            'status' => $trip->seal->status,
            'sepio_status' => $trip->seal->sepio_status,
            'last_scan_at' => $trip->seal->last_scan_at,
            'latest_log' => $latestLog,
        ]);
    }
}
