<?php

namespace App\Http\Controllers;

use App\Http\Requests\Wallet\SettleCreditRequest;
use App\Http\Requests\Wallet\StoreWalletRequest;
use App\Http\Requests\Wallet\TopUpWalletRequest;
use App\Http\Requests\Wallet\UpdateWalletRequest;
use App\Http\Resources\WalletResource;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Services\WalletService;
use Illuminate\Http\JsonResponse;

class CustomerWalletController extends Controller
{
    public function __construct(private readonly WalletService $service)
    {
    }

    public function show(Customer $customer): JsonResponse
    {
        $this->authorize('view', $customer->wallet ?? new CustomerWallet(['customer_id' => $customer->id]));

        $wallet = $customer->wallet()->with('pricingTiers', 'tripPricingRules')->first();
        abort_if(!$wallet, 404, 'Wallet not configured for this customer.');

        return response()->json(new WalletResource($wallet));
    }

    public function store(StoreWalletRequest $request, Customer $customer): JsonResponse
    {
        $this->authorize('manage', CustomerWallet::class);

        abort_if($customer->wallet()->exists(), 422, 'Wallet already exists for this customer.');

        $wallet = $this->service->store($customer, $request->validated(), $request->user());

        return response()->json(new WalletResource($wallet), 201);
    }

    public function update(UpdateWalletRequest $request, Customer $customer): JsonResponse
    {
        $wallet = $customer->wallet;
        abort_if(!$wallet, 404, 'Wallet not configured for this customer.');

        $this->authorize('manage', CustomerWallet::class);

        $wallet = $this->service->update($wallet, $request->validated());

        return response()->json(new WalletResource($wallet));
    }

    public function topUp(TopUpWalletRequest $request, Customer $customer): JsonResponse
    {
        $wallet = $customer->wallet;
        abort_if(!$wallet, 404, 'Wallet not configured for this customer.');
        $this->authorize('manage', CustomerWallet::class);

        $receiptFilePath = null;
        if ($request->hasFile('receipt_file')) {
            $receiptFilePath = $request->file('receipt_file')
                ->store("customers/{$customer->id}/wallet/receipts");
        }

        $wallet = $this->service->topUp(
            $wallet,
            $request->amount,
            $request->user(),
            $request->remarks,
            $receiptFilePath
        );

        return response()->json(new WalletResource($wallet));
    }

    public function settleCredit(SettleCreditRequest $request, Customer $customer): JsonResponse
    {
        $wallet = $customer->wallet;
        abort_if(!$wallet, 404, 'Wallet not configured for this customer.');
        $this->authorize('manage', CustomerWallet::class);

        $receiptFilePath = null;
        if ($request->hasFile('receipt_file')) {
            $receiptFilePath = $request->file('receipt_file')
                ->store("customers/{$customer->id}/wallet/receipts");
        }

        $wallet = $this->service->settleCredit(
            $wallet,
            $request->amount,
            $request->user(),
            $receiptFilePath
        );

        return response()->json(new WalletResource($wallet));
    }

    public function transactions(Customer $customer): JsonResponse
    {
        $wallet = $customer->wallet;
        abort_if(!$wallet, 404);

        $this->authorize('view', $wallet);

        $transactions = $wallet->transactions()->latest('created_at')->paginate(30);

        return response()->json(
            $transactions->through(fn($t) => [
                'id' => $t->id,
                'type' => $t->type,
                'amount' => $t->amount,
                'reference_type' => $t->reference_type,
                'reference_id' => $t->reference_id,
                'trip_id' => $t->trip_id,
                'balance_after' => $t->balance_after,
                'balance_type' => $t->balance_type,
                'receipt_file_url' => $t->receipt_file_url
                    ? \Storage::temporaryUrl($t->receipt_file_url, now()->addMinutes(30))
                    : null,
                'created_at' => $t->created_at,
            ])
        );
    }
}
