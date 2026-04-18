<?php

namespace App\Http\Controllers;

use App\Http\Requests\Route\StoreRouteRequest;
use App\Http\Requests\Route\UpdateRouteRequest;
use App\Http\Resources\CustomerRouteResource;
use App\Models\CustomerRoute;
use App\Services\RouteService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CustomerRouteController extends Controller
{
    public function __construct(private readonly RouteService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', CustomerRoute::class);

        $routes = QueryBuilder::for(CustomerRoute::class)
            ->allowedFilters([
                AllowedFilter::exact('trip_type'),
                AllowedFilter::exact('transport_mode'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where('name', 'like', "%{$value}%");
                }),
            ])
            ->allowedSorts(['name', 'trip_type', 'transport_mode', 'created_at'])
            ->allowedIncludes(['customer'])
            ->defaultSort('name')
            ->paginate($request->query('per_page', 30))
            ->appends($request->query());

        return CustomerRouteResource::collection($routes);
    }

    public function store(StoreRouteRequest $request): JsonResponse
    {
        $this->authorize('create', CustomerRoute::class);

        $route = $this->service->store($request->validated(), $request->user());

        return response()->json(new CustomerRouteResource($route), 201);
    }

    public function show(CustomerRoute $route): JsonResponse
    {
        $this->authorize('view', $route);

        return response()->json(new CustomerRouteResource($route));
    }

    public function update(UpdateRouteRequest $request, CustomerRoute $route): JsonResponse
    {
        $this->authorize('update', $route);

        $route = $this->service->update($route, $request->validated(), $request->user());

        return response()->json(new CustomerRouteResource($route));
    }

    public function destroy(CustomerRoute $route): JsonResponse
    {
        $this->authorize('delete', $route);

        $this->service->delete($route);

        return response()->json(['message' => 'Route deleted.']);
    }

    public function toggleActive(CustomerRoute $route): JsonResponse
    {
        $this->authorize('update', $route);

        $route = $this->service->toggleActive($route);

        return response()->json(new CustomerRouteResource($route));
    }
}
