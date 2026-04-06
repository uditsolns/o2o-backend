<?php

namespace App\Http\Controllers;

use App\Http\Requests\Location\StoreLocationRequest;
use App\Http\Requests\Location\UpdateLocationRequest;
use App\Http\Resources\CustomerLocationResource;
use App\Models\CustomerLocation;
use App\Services\LocationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CustomerLocationController extends Controller
{
    public function __construct(private readonly LocationService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CustomerLocation::class);

        $locations = QueryBuilder::for(CustomerLocation::class)
            ->allowedFilters([
                AllowedFilter::exact('location_type'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('city', 'like', "%{$value}%")
                            ->orWhere('state', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['name', 'city', 'location_type', 'created_at'])
            ->allowedIncludes(['createdBy'])
            ->defaultSort('name')
            ->paginate($request->input('perPage', 30))
            ->appends($request->query());

        return CustomerLocationResource::collection($locations);
    }

    public function store(StoreLocationRequest $request): JsonResponse
    {
        $this->authorize('create', CustomerLocation::class);

        $location = $this->service->store($request->validated(), $request->user());

        return response()->json(new CustomerLocationResource($location), 201);
    }

    public function show(CustomerLocation $location): JsonResponse
    {
        $this->authorize('view', $location);

        return response()->json(new CustomerLocationResource(
            $location->loadMissing('createdBy')
        ));
    }

    public function update(UpdateLocationRequest $request, CustomerLocation $location): JsonResponse
    {
        $this->authorize('update', $location);

        $location = $this->service->update($location, $request->validated());

        return response()->json(new CustomerLocationResource($location));
    }

    public function destroy(CustomerLocation $location): JsonResponse
    {
        $this->authorize('delete', $location);

        $this->service->delete($location);

        return response()->json(['message' => 'Location deleted.']);
    }

    public function toggleActive(CustomerLocation $location): JsonResponse
    {
        $this->authorize('update', $location);

        $location = $this->service->toggleActive($location);

        return response()->json(new CustomerLocationResource($location));
    }
}
