<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\CustomerWalletTransaction;
use App\Models\SealPricingTier;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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

    public function topUp(CustomerWallet $wallet, float $amount, User $by, ?string $remarks = null): CustomerWallet
    {
        return DB::transaction(function () use ($wallet, $amount, $by, $remarks) {
            $newBalance = $wallet->cost_balance + $amount;

            $wallet->update(['cost_balance' => $newBalance]);

            CustomerWalletTransaction::create([
                'wallet_id' => $wallet->id,
                'customer_id' => $wallet->customer_id,
                'type' => 'credit',
                'amount' => $amount,
                'reference_type' => 'manual',
                'balance_after' => $newBalance,
            ]);

            return $wallet->fresh();
        });
    }

    /**
     * Replace all pricing tiers for a customer — sent as a batch.
     */
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

    /**
     * Resolve unit price for a given quantity from active tiers.
     */
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
        // Sort by min, then check no range overlaps with the next
        usort($tiers, fn($a, $b) => $a['min_quantity'] <=> $b['min_quantity']);

        for ($i = 0; $i < count($tiers) - 1; $i++) {
            $current = $tiers[$i];
            $next = $tiers[$i + 1];

            // Open-ended tier (null max) must be last
            if (is_null($current['max_quantity'] ?? null)) {
                abort(422, 'Only the last pricing tier can have an open-ended max quantity.');
            }

            if ($current['max_quantity'] >= $next['min_quantity']) {
                abort(422, "Pricing tier ranges overlap: {$current['min_quantity']}–{$current['max_quantity']} overlaps with {$next['min_quantity']}.");
            }
        }
    }

    public function debit(CustomerWallet $wallet, float $amount, string $referenceType, int $referenceId): void
    {
        DB::transaction(function () use ($wallet, $amount, $referenceType, $referenceId) {
            $newBalance = $wallet->cost_balance - $amount;

            $wallet->update(['cost_balance' => $newBalance]);

            CustomerWalletTransaction::create([
                'wallet_id' => $wallet->id,
                'customer_id' => $wallet->customer_id,
                'type' => 'debit',
                'amount' => $amount,
                'reference_type' => $referenceType,
                'reference_id' => $referenceId,
                'balance_after' => $newBalance,
            ]);
        });
    }
}
