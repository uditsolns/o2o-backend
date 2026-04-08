<?php

namespace Database\Seeders;

use App\Enums\CustomerOnboardingStatus;
use App\Models\Customer;
use App\Models\CustomerWalletTransaction;
use Illuminate\Database\Seeder;

/**
 * Seeds wallet transaction history (credits + debits) for customers
 * with existing wallets, so the transaction ledger is non-empty.
 */
class WalletTransactionSeeder extends Seeder
{
    public function run(): void
    {
        $customers = Customer::where('onboarding_status', CustomerOnboardingStatus::Completed->value)
            ->with('wallet')
            ->get();

        $total = 0;

        foreach ($customers as $customer) {
            $wallet = $customer->wallet;
            if (!$wallet) continue;

            // Skip if already has transactions
            if ($wallet->transactions()->exists()) continue;

            $entries = [
                [
                    'type' => 'credit',
                    'amount' => 5_00_000.00,
                    'reference_type' => 'manual',
                    'reference_id' => null,
                    'balance_after' => 5_00_000.00,
                    'created_at' => now()->subDays(60),
                ],
                [
                    'type' => 'debit',
                    'amount' => 1_20_000.00,
                    'reference_type' => 'order',
                    'reference_id' => null,
                    'balance_after' => 3_80_000.00,
                    'created_at' => now()->subDays(45),
                ],
                [
                    'type' => 'credit',
                    'amount' => 2_00_000.00,
                    'reference_type' => 'manual',
                    'reference_id' => null,
                    'balance_after' => 5_80_000.00,
                    'created_at' => now()->subDays(30),
                ],
                [
                    'type' => 'debit',
                    'amount' => 3_30_000.00,
                    'reference_type' => 'order',
                    'reference_id' => null,
                    'balance_after' => 2_50_000.00,
                    'created_at' => now()->subDays(15),
                ],
            ];

            foreach ($entries as $entry) {
                CustomerWalletTransaction::insert([
                    'wallet_id' => $wallet->id,
                    'customer_id' => $customer->id,
                    'type' => $entry['type'],
                    'amount' => $entry['amount'],
                    'reference_type' => $entry['reference_type'],
                    'reference_id' => $entry['reference_id'],
                    'balance_after' => $entry['balance_after'],
                    'created_at' => $entry['created_at'],
                ]);
                $total++;
            }
        }

        $this->command->info("  WalletTransactionSeeder: {$total} transactions seeded.");
    }
}
