<?php

namespace App\Http\Controllers;

use App\Http\Requests\CustomerPort\StoreCustomerPortRequest;
use App\Http\Requests\CustomerPort\UpdateCustomerPortRequest;
use App\Http\Resources\CustomerPortResource;
use App\Models\CustomerPort;
use App\Services\CustomerPortService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class CustomerPortController extends Controller
{
    public function __construct(private readonly CustomerPortService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $ports = QueryBuilder::for(CustomerPort::class)
            ->allowedFilters([
                AllowedFilter::exact('port_category'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('name', 'like', "%{$value}%")
                            ->orWhere('code', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['name', 'code', 'port_category', 'created_at'])
            ->allowedIncludes(['port'])
            ->defaultSort('name')
            ->paginate($request->query('per_page', 50))
            ->appends($request->query());

        return CustomerPortResource::collection($ports);
    }

    public function store(StoreCustomerPortRequest $request): JsonResponse
    {
        $customerPort = $this->service->store($request->validated(), $request->user());

        return response()->json(new CustomerPortResource($customerPort), 201);
    }

    public function show(CustomerPort $customerPort): JsonResponse
    {
        $this->authorizeOwnership($customerPort, request()->user());

        return response()->json(new CustomerPortResource(
            $customerPort->loadMissing('port')
        ));
    }

    public function update(UpdateCustomerPortRequest $request, CustomerPort $customerPort): JsonResponse
    {
        $this->authorizeOwnership($customerPort, $request->user());

        $customerPort = $this->service->update($customerPort, $request->validated());

        return response()->json(new CustomerPortResource($customerPort));
    }

    public function destroy(CustomerPort $customerPort): JsonResponse
    {
        $this->authorizeOwnership($customerPort, request()->user());

        $this->service->delete($customerPort);

        return response()->json(['message' => 'Port removed from your account.']);
    }

    private function authorizeOwnership(CustomerPort $customerPort, $user): void
    {
        if ($user->isPlatformUser()) return;

        if ($customerPort->customer_id !== $user->customer_id) {
            abort(403);
        }
    }

    public function toggleActive(CustomerPort $customerPort): JsonResponse
    {
        $this->authorizeOwnership($customerPort, request()->user());

        $port = $this->service->toggleActive($customerPort);

        return response()->json(new CustomerPortResource($port));
    }
}
