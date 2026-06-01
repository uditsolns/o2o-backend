<?php

namespace App\Services;

use App\Enums\TripTransportationMode;
use App\Enums\TripType;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\SealPricingTier;
use App\Models\Trip;
use App\Models\TripPricingRule;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class WalletService
{
    public function store(Customer $customer, array $data, User $createdBy): CustomerWallet
    {
        return CustomerWallet::create([
            ...$data,
            'customer_id' => $customer->id,
            'created_by_id' => $createdBy->id,
        ]);
    }

    public function update(CustomerWallet $wallet, array $data): CustomerWallet
    {
        $wallet->update($data);
        return $wallet->fresh();
    }

    /**
     * Manual top-up (credit to cost_balance).
     */
    public function topUp(CustomerWallet $wallet, float $amount, User $by, ?string $remarks = null, ?string $receiptFilePath = null): CustomerWallet
    {
        return DB::transaction(function () use ($wallet, $amount, $by, $remarks, $receiptFilePath) {
            $newBalance = $wallet->cost_balance + $amount;
            $wallet->update(['cost_balance' => $newBalance]);

            CustomerWalletTransaction::create([
                'wallet_id' => $wallet->id,
                'customer_id' => $wallet->customer_id,
                'type' => 'credit',
                'amount' => $amount,
                'reference_type' => 'manual_topup',
                'balance_after' => $newBalance,
                'balance_type' => 'cost_balance',
                'receipt_file_url' => $receiptFilePath,
            ]);

            return $wallet->fresh();
        });
    }

    public function debit(CustomerWallet $wallet, float $amount, string $referenceType, int $referenceId, ?int $tripId = null): void
    {
        DB::transaction(function () use ($wallet, $amount, $referenceType, $referenceId, $tripId) {
            $newBalance = $wallet->cost_balance - $amount;
            $wallet->update(['cost_balance' => $newBalance]);

            CustomerWalletTransaction::create([
                'wallet_id' => $wallet->id,
                'customer_id' => $wallet->customer_id,
                'type' => 'debit',
                'amount' => $amount,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'trip_id' => $tripId,
                'balance_after' => $newBalance,
                'balance_type' => 'cost_balance',
            ]);
        });
    }

    public function credit(CustomerWallet $wallet, float $amount, string $referenceType, int $referenceId): void
    {
        DB::transaction(function () use ($wallet, $amount, $referenceType, $referenceId) {
            $newBalance = $wallet->cost_balance + $amount;
            $wallet->update(['cost_balance' => $newBalance]);

            CustomerWalletTransaction::create([
                'wallet_id' => $wallet->id,
                'customer_id' => $wallet->customer_id,
                'type' => 'credit',
                'amount' => $amount,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'balance_after' => $newBalance,
                'balance_type' => 'cost_balance',
            ]);
        });
    }

    public function drawCredit(CustomerWallet $wallet, float $amount, int $orderId): void
    {
        DB::transaction(function () use ($wallet, $amount, $orderId) {
            $wallet->increment('credit_used', $amount);
            $wallet->refresh();
            $availableCredit = $wallet->credit_capping - $wallet->credit_used;

            CustomerWalletTransaction::create([
                'wallet_id' => $wallet->id,
                'customer_id' => $wallet->customer_id,
                'type' => 'debit',
                'amount' => $amount,
                'reference_type' => 'credit_draw',
                'reference_id' => $orderId,
                'balance_after' => $availableCredit,   // available credit remaining
                'balance_type' => 'available_credit',
            ]);
        });
    }

    public function releaseCredit(CustomerWallet $wallet, float $amount, int $orderId): void
    {
        DB::transaction(function () use ($wallet, $amount, $orderId) {
            $wallet->decrement('credit_used', $amount);
            $wallet->refresh();
            $availableCredit = $wallet->credit_capping - $wallet->credit_used;

            CustomerWalletTransaction::create([
                'wallet_id' => $wallet->id,
                'customer_id' => $wallet->customer_id,
                'type' => 'credit',
                'amount' => $amount,
                'reference_type' => 'credit_release',
                'reference_id' => $orderId,
                'balance_after' => $availableCredit,
                'balance_type' => 'available_credit',
            ]);
        });
    }

    public function settleCredit(CustomerWallet $wallet, float $amount, User $by, ?string $receiptFilePath = null): CustomerWallet
    {
        abort_if(
            $amount > $wallet->credit_used,
            422,
            "Settlement amount ₹{$amount} exceeds outstanding credit ₹{$wallet->credit_used}."
        );

        return DB::transaction(function () use ($wallet, $amount, $by, $receiptFilePath) {
            $wallet->decrement('credit_used', $amount);
            $wallet->refresh();
            $availableCredit = $wallet->credit_capping - $wallet->credit_used;

            CustomerWalletTransaction::create([
                'wallet_id' => $wallet->id,
                'customer_id' => $wallet->customer_id,
                'type' => 'credit',
                'amount' => $amount,
                'reference_type' => 'credit_settlement',
                'balance_after' => $availableCredit,
                'balance_type' => 'available_credit',
                'receipt_file_url' => $receiptFilePath,
            ]);

            return $wallet->fresh();
        });
    }

    /**
     * Deduct trip cost from wallet on trip completion.
     * Waterfall: customer-specific rule → global rule → null (no charge).
     */
    public function deductTripCost(CustomerWallet $wallet, Trip $trip): ?float
    {
        $price = $this->resolveTripPrice($wallet->customer_id, $trip);

        if ($price === null || $price <= 0) {
            return null;
        }

        // Log warning if balance would go negative — don't block the ePOD
        if ($wallet->cost_balance < $price) {
            Log::warning('Trip cost deduction: insufficient balance, balance will go negative', [
                'customer_id' => $wallet->customer_id,
                'trip_id' => $trip->id,
                'price' => $price,
                'cost_balance' => $wallet->cost_balance,
            ]);
        }

        $this->debit($wallet, $price, 'trip_debit', $trip->id, $trip->id);

        return $price;
    }

    public function resolveTripPrice(int $customerId, Trip $trip): ?float
    {
        $tripType = $trip->trip_type instanceof TripType
            ? $trip->trip_type->value
            : $trip->trip_type;
        $transportMode = $trip->transport_mode instanceof TripTransportationMode
            ? $trip->transport_mode->value
            : $trip->transport_mode;

        // Customer-specific rule first
        $rule = TripPricingRule::where('customer_id', $customerId)
            ->where('trip_type', $tripType)
            ->where('transport_mode', $transportMode)
            ->where('is_active', true)
            ->first();

        // Fall back to global rule
        if (!$rule) {
            $rule = TripPricingRule::whereNull('customer_id')
                ->where('trip_type', $tripType)
                ->where('transport_mode', $transportMode)
                ->where('is_active', true)
                ->first();
        }

        return $rule ? (float)$rule->price_per_trip : null;
    }

    public function syncPricingTiers(Customer $customer, array $tiers, User $createdBy): void
    {
        $this->validateNoOverlap($tiers);

        DB::transaction(function () use ($customer, $tiers, $createdBy) {
            SealPricingTier::where('customer_id', $customer->id)->delete();

            foreach ($tiers as $tier) {
                SealPricingTier::create([
                    'customer_id' => $customer->id,
                    'min_quantity' => $tier['min_quantity'],
                    'max_quantity' => $tier['max_quantity'] ?? null,
                    'price_per_seal' => $tier['price_per_seal'],
                    'is_active' => true,
                    'created_by_id' => $createdBy->id,
                ]);
            }
        });
    }

    public function resolvePriceForQuantity(Customer $customer, int $quantity): ?float
    {
        $tier = SealPricingTier::where('customer_id', $customer->id)
            ->where('is_active', true)
            ->where('min_quantity', '<=', $quantity)
            ->where(fn($q) => $q->whereNull('max_quantity')->orWhere('max_quantity', '>=', $quantity))
            ->first();

        return $tier?->price_per_seal;
    }

    private function validateNoOverlap(array $tiers): void
    {
        usort($tiers, fn($a, $b) => $a['min_quantity'] <=> $b['min_quantity']);

        for ($i = 0; $i < count($tiers) - 1; $i++) {
            $current = $tiers[$i];
            $next = $tiers[$i + 1];

            if (is_null($current['max_quantity'] ?? null)) {
                abort(422, 'Only the last pricing tier can have an open-ended max quantity.');
            }

            if ($current['max_quantity'] >= $next['min_quantity']) {
                abort(422, "Pricing tier ranges overlap: {$current['min_quantity']}–{$current['max_quantity']} overlaps with {$next['min_quantity']}.");
            }
        }
    }
}
