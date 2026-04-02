<?php

namespace App\Http\Controllers;

use App\Http\Requests\SealOrder\SealOrderActionRequest;
use App\Http\Requests\SealOrder\StoreSealOrderRequest;
use App\Http\Resources\SealOrderResource;
use App\Models\SealOrder;
use App\Services\SealOrderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class SealOrderController extends Controller
{
    public function __construct(private readonly SealOrderService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', SealOrder::class);

        $orders = QueryBuilder::for(SealOrder::class)
            ->allowedFilters([
                AllowedFilter::exact('status'),
                AllowedFilter::exact('payment_type'),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('order_ref', 'like', "%{$value}%")
                            ->orWhere('sepio_order_id', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['ordered_at', 'total_amount', 'status'])
            ->allowedIncludes(['billingLocation', 'shippingLocation', 'orderedBy', 'ilApprovedBy'])
            ->defaultSort('-ordered_at')
            ->paginate(20)
            ->appends($request->query());

        return SealOrderResource::collection($orders);
    }

    public function store(StoreSealOrderRequest $request): JsonResponse
    {
        $this->authorize('create', SealOrder::class);

        $order = $this->service->store($request->validated(), $request->user());

        return response()->json(new SealOrderResource(
            $order->load('billingLocation', 'shippingLocation', 'orderedBy')
        ), 201);
    }

    public function show(SealOrder $order): JsonResponse
    {
        $this->authorize('view', $order);

        return response()->json(new SealOrderResource(
            $order->load('billingLocation', 'shippingLocation', 'orderedBy', 'ilApprovedBy')
        ));
    }

    public function approve(SealOrderActionRequest $request, SealOrder $order): JsonResponse
    {
        $this->authorize('approve', $order);

        $order = $this->service->approve($order, $request->validated(), $request->user());

        return response()->json(new SealOrderResource($order));
    }

    public function reject(SealOrderActionRequest $request, SealOrder $order): JsonResponse
    {
        $this->authorize('reject', $order);

        $order = $this->service->reject($order, $request->validated(), $request->user());

        return response()->json(new SealOrderResource($order));
    }

    public function park(SealOrderActionRequest $request, SealOrder $order): JsonResponse
    {
        $this->authorize('park', $order);

        $order = $this->service->park($order, $request->validated(), $request->user());

        return response()->json(new SealOrderResource($order));
    }
}
