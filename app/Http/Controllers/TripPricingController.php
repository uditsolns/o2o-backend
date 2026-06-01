<?php

namespace App\Http\Controllers;

use App\Http\Requests\TripPricing\StoreTripPricingRequest;
use App\Models\TripPricingRule;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

class TripPricingController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $query = QueryBuilder::for(TripPricingRule::class)
            ->allowedFilters([
                AllowedFilter::exact('trip_type'),
                AllowedFilter::exact('transport_mode'),
                AllowedFilter::exact('is_active'),
                AllowedFilter::exact('customer_id'),
            ])
            ->allowedSorts(['trip_type', 'transport_mode', 'price_per_trip', 'created_at'])
            ->defaultSort('trip_type');

        if ($user->isClientUser()) {
            $customerId = $user->customer_id;

            $query->where(function ($q) use ($customerId) {
                // Show customer's own overrides
                $q->where('customer_id', $customerId)

                    // Show global rules ONLY where no customer-specific override exists
                    // for the same trip_type + transport_mode combination
                    ->orWhere(function ($q2) use ($customerId) {
                        $q2->whereNull('customer_id')
                            ->whereNotExists(function ($sub) use ($customerId) {
                                $sub->select(DB::raw(1))
                                    ->from('trip_pricing_rules as overrides')
                                    ->where('overrides.customer_id', $customerId)
                                    ->where('overrides.is_active', true)
                                    ->whereColumn('overrides.trip_type', 'trip_pricing_rules.trip_type')
                                    ->whereColumn('overrides.transport_mode', 'trip_pricing_rules.transport_mode');
                            });
                    });
            });
        }
        // Platform users: no automatic scoping — all rules visible,
        // filtered by query params if needed (filter[customer_id], etc.)

        $rules = $query
            ->paginate($request->query('per_page', 50))
            ->appends($request->query());

        return response()->json($rules);
    }

    public function sync(StoreTripPricingRequest $request): JsonResponse
    {
        $this->authorize('trip_pricing.manage');

        $customerId = $request->input('customer_id');

        DB::transaction(function () use ($request, $customerId) {
            TripPricingRule::where('customer_id', $customerId)->delete();

            foreach ($request->validated('rules') as $rule) {
                TripPricingRule::create([
                    'customer_id' => $customerId,
                    'trip_type' => $rule['trip_type'],
                    'transport_mode' => $rule['transport_mode'],
                    'price_per_trip' => $rule['price_per_trip'],
                    'is_active' => true,
                    'created_by_id' => $request->user()->id,
                ]);
            }
        });

        return response()->json(
            TripPricingRule::where('customer_id', $customerId)
                ->where('is_active', true)
                ->get()
        );
    }
}
