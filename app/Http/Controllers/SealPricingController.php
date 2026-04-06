<?php

namespace App\Http\Controllers;

use App\Http\Requests\Pricing\StorePricingTierRequest;
use App\Models\Customer;
use App\Models\SealPricingTier;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SealPricingController extends Controller
{
    public function __construct(private readonly WalletService $service)
    {
    }

    /**
     * Client reads own tiers; IL reads via /customers/{customer}/pricing.
     */
    public function index(Request $request): JsonResponse
    {
        $customerId = $request->user()->isClientUser()
            ? $request->user()->customer_id
            : $request->route('customer');

        abort_if(!$customerId, 400, 'Customer context required.');

        $tiers = SealPricingTier::where('customer_id', $customerId)
            ->where('is_active', true)
            ->orderBy('min_quantity')
            ->get(['id', 'min_quantity', 'max_quantity', 'price_per_seal']);

        return response()->json($tiers);
    }

    /**
     * IL syncs all tiers for a customer in one call.
     */
    public function sync(StorePricingTierRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('create', SealPricingTier::class);

        $this->service->syncPricingTiers($customer, $request->validated('tiers'), $request->user());

        $tiers = SealPricingTier::where('customer_id', $customer->id)
            ->where('is_active', true)
            ->orderBy('min_quantity')
            ->get();

        return response()->json($tiers);
    }

    /**
     * Calculate order cost preview without placing the order.
     */
    public function calculate(Request $request): JsonResponse
    {
        $request->validate(['quantity' => ['required', 'integer', 'min:20']]);

        $customer = $request->user()->customer;
        abort_if(is_null($customer), 400, 'This action is unauthorized.');

        $wallet = $customer->wallet;
        abort_if(!$wallet, 422, 'Wallet not configured.');

        $unitPrice = $this->service->resolvePriceForQuantity($customer, $request->quantity);
        abort_if(!$unitPrice, 422, 'No pricing tier found for this quantity.');

        $sealCost = round($request->quantity * $unitPrice, 2);
        $freightAmount = round($request->quantity * $wallet->freight_rate_per_seal, 2);
        $gstAmount = round(($sealCost + $freightAmount) * 0.18, 2);

        return response()->json([
            'quantity' => $request->quantity,
            'unit_price' => $unitPrice,
            'seal_cost' => $sealCost,
            'freight_amount' => $freightAmount,
            'gst_amount' => $gstAmount,
            'total_amount' => round($sealCost + $freightAmount + $gstAmount, 2),
        ]);
    }
}
