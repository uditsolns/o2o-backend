<?php

namespace App\Http\Controllers;

use App\Http\Requests\Seal\IngestSealsRequest;
use App\Http\Resources\SealResource;
use App\Http\Resources\SealStatusLogResource;
use App\Models\Seal;
use App\Models\SealOrder;
use App\Services\SealService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SealController extends Controller
{
    public function __construct(private readonly SealService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Seal::class);

        $seals = QueryBuilder::for(Seal::class)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('sepio_status'),
                AllowedFilter::exact('seal_order_id'),
                AllowedFilter::exact('trip_id'),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where('seal_number', 'like', "%{$value}%");
                }),
            ])
            ->allowedSorts(['seal_number', 'status', 'last_scan_at', 'created_at'])
            ->allowedIncludes(['order', 'trip'])
            ->defaultSort('-created_at')
            ->paginate($request->input('perPage', 50))
            ->appends($request->query());

        return SealResource::collection($seals);
    }

    public function show(Seal $seal): JsonResponse
    {
        $this->authorize('view', $seal);

        return response()->json(new SealResource(
            $seal->load('order', 'trip')
        ));
    }

    public function statusHistory(Seal $seal): AnonymousResourceCollection
    {
        $this->authorize('view', $seal);

        $logs = $seal->statusLogs()
            ->orderByDesc('checked_at')
            ->paginate(50);

        return SealStatusLogResource::collection($logs);
    }

    /**
     * IL only — ingest seal numbers after Sepio dispatches an order.
     * POST /orders/{order}/seals
     */
    public function ingest(IngestSealsRequest $request, SealOrder $order): JsonResponse
    {
        $this->authorize('approve', $order); // IL permission gate

        $this->service->ingestFromOrder(
            $order,
            $request->validated('seal_numbers'),
            $request->validated('dispatched_at')
        );

        return response()->json([
            'message' => $order->quantity . ' seals ingested successfully.',
            'order_ref' => $order->order_ref,
        ]);
    }
}
