<?php

namespace App\Http\Controllers;

use App\Http\Requests\Port\StorePortRequest;
use App\Http\Requests\Port\UpdatePortRequest;
use App\Http\Resources\PortResource;
use App\Models\Port;
use App\Services\PortService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class PortController extends Controller
{
    public function __construct(private readonly PortService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Port::class);

        $ports = QueryBuilder::for(Port::class)
            ->allowedFilters([
                AllowedFilter::exact('port_category'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('country'),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('code', 'like', "%{$value}%")
                            ->orWhere('city', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['name', 'code', 'port_category', 'created_at'])
            ->defaultSort('name')
            ->paginate($request->input('perPage', 50))
            ->appends($request->query());

        return PortResource::collection($ports);
    }

    public function store(StorePortRequest $request): JsonResponse
    {
        $this->authorize('create', Port::class);

        $port = $this->service->store($request->validated(), $request->user());

        return response()->json(new PortResource($port), 201);
    }

    public function show(Port $port): JsonResponse
    {
        $this->authorize('view', $port);

        return response()->json(new PortResource($port));
    }

    public function update(UpdatePortRequest $request, Port $port): JsonResponse
    {
        $this->authorize('update', $port);

        $port = $this->service->update($port, $request->validated());

        return response()->json(new PortResource($port));
    }

    public function toggleActive(Port $port): JsonResponse
    {
        $this->authorize('update', $port);

        $port = $this->service->toggleActive($port);

        return response()->json(new PortResource($port));
    }
}
