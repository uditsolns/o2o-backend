<?php

namespace App\Http\Controllers;

use App\Http\Requests\Report\ReportFilterRequest;
use App\Services\DashboardService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function __construct(private readonly DashboardService $service)
    {
    }

    public function stats(Request $request): JsonResponse
    {
        $user = $request->user();

        $stats = $user->isPlatformUser()
            ? $this->service->platformStats()
            : $this->service->clientStats($user->customer_id);

        return response()->json($stats);
    }

    public function tripsReport(ReportFilterRequest $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Trip::class);

        // Client users: force-scope to own customer, ignore any passed customer_id
        $filters = $request->validated();
        if ($request->user()->isClientUser()) {
            $filters['customer_id'] = $request->user()->customer_id;
        }

        return response()->json(
            $this->service->tripsReport($filters)
        );
    }

    public function sealsReport(ReportFilterRequest $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\Seal::class);

        $filters = $request->validated();
        if ($request->user()->isClientUser()) {
            $filters['customer_id'] = $request->user()->customer_id;
        }

        return response()->json(
            $this->service->sealsReport($filters)
        );
    }

    public function ordersReport(ReportFilterRequest $request): JsonResponse
    {
        $this->authorize('viewAny', \App\Models\SealOrder::class);

        $filters = $request->validated();
        if ($request->user()->isClientUser()) {
            $filters['customer_id'] = $request->user()->customer_id;
        }

        return response()->json(
            $this->service->ordersReport($filters)
        );
    }
}
