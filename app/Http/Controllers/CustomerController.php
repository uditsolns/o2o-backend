<?php

namespace App\Http\Controllers;

use App\Http\Requests\Customer\CustomerActionRequest;
use App\Http\Requests\Customer\StoreCustomerRequest;
use App\Http\Requests\Customer\UpdateCustomerRequest;
use App\Http\Resources\CustomerResource;
use App\Models\Customer;
use App\Services\CustomerService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CustomerController extends Controller
{
    public function __construct(private readonly CustomerService $service)
    {
    }

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Customer::class);

        $customers = QueryBuilder::for(Customer::class)
            ->allowedFilters([
                AllowedFilter::exact('onboarding_status'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::callback('search', function ($query, $value) {
                    $query->where(function ($q) use ($value) {
                        $q->where('company_name', 'like', "%{$value}%")
                            ->orWhere('email', 'like', "%{$value}%")
                            ->orWhere('iec_number', 'like', "%{$value}%");
                    });
                }),
            ])
            ->allowedSorts(['company_name', 'created_at', 'onboarding_status'])
            ->allowedIncludes(['approvedBy', 'wallet'])
            ->defaultSort('-created_at')
            ->paginate(20)
            ->appends($request->query());

        return CustomerResource::collection($customers);
    }

    public function store(StoreCustomerRequest $request): JsonResponse
    {
        $this->authorize('create', Customer::class);

        $customer = $this->service->store($request->validated(), $request->user());

        return response()->json(new CustomerResource($customer), 201);
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        $customer->load('approvedBy', 'wallet');

        return response()->json(new CustomerResource($customer));
    }

    public function update(UpdateCustomerRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $customer = $this->service->update($customer, $request->validated());

        return response()->json(new CustomerResource($customer));
    }

    public function approve(CustomerActionRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('approve', $customer);

        $customer = $this->service->approve($customer, $request->validated(), $request->user());

        return response()->json(new CustomerResource($customer));
    }

    public function reject(CustomerActionRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('reject', $customer);

        $customer = $this->service->reject($customer, $request->validated(), $request->user());

        return response()->json(new CustomerResource($customer));
    }

    public function park(CustomerActionRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('park', $customer);

        $customer = $this->service->park($customer, $request->validated(), $request->user());

        return response()->json(new CustomerResource($customer));
    }

    public function toggleActive(Customer $customer): JsonResponse
    {
        $this->authorize('update', $customer);

        $customer = $this->service->toggleActive($customer);

        return response()->json(new CustomerResource($customer));
    }

    public function documents(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return response()->json($customer->documents()->latest()->get());
    }

    public function seals(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return response()->json(
            $customer->seals()->with('order')->latest()->paginate(50)
        );
    }

    public function orders(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return response()->json(
            $customer->sealOrders()->latest('ordered_at')->paginate(20)
        );
    }

    public function trips(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer);

        return response()->json(
            $customer->trips()->latest()->paginate(20)
        );
    }
}
