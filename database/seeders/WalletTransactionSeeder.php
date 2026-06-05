<?php

namespace Database\Seeders;

use App\Enums\CustomerOnboardingStatus;
use App\Models\Customer;
use App\Models\CustomerWalletTransaction;
use App\Models\SealOrder;
use App\Models\Trip;
use Illuminate\Database\Seeder;

/**
 * Seeds wallet transaction history (credits + debits) for customers
 * with existing wallets, so the transaction ledger is non-empty.
 *
 * Uses the *new* reference_type values (per migration 2026_06_01_135809):
 *   manual_topup, trip_debit, trip_refund, advance_debit, advance_refund,
 *   credit_draw, credit_release, credit_settlement, cash_payment_received
 *
 * Also populates balance_type and (where appropriate) trip_id, receipt_file_url.
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

            // Reference a real seal order for advance_debit rows (if any)
            $firstOrder = SealOrder::where('customer_id', $customer->id)
                ->where('payment_type', 'advance_balance')
                ->orderBy('id')
                ->first();

            // Reference a real trip for trip_debit row
            $firstTrip = Trip::where('customer_id', $customer->id)
                ->whereIn('status', ['completed', 'delivered'])
                ->orderBy('id')
                ->first();

            // ── Ledger entries — running balance for cost_balance ──────────
            $entries = [
                // Initial topup
                [
                    'type' => 'credit',
                    'amount' => 5_00_000.00,
                    'reference_type' => 'manual_topup',
                    'reference_id' => null,
                    'trip_id' => null,
                    'balance_after' => 5_00_000.00,
                    'balance_type' => 'cost_balance',
                    'receipt_file_url' => 'receipts/' . $customer->id . '/topup-001.pdf',
                    'created_at' => now()->subDays(60),
                ],
                // Order debit (advance)
                [
                    'type' => 'debit',
                    'amount' => 1_20_000.00,
                    'reference_type' => 'advance_debit',
                    'reference_id' => $firstOrder?->id,
                    'trip_id' => null,
                    'balance_after' => 3_80_000.00,
                    'balance_type' => 'cost_balance',
                    'receipt_file_url' => null,
                    'created_at' => now()->subDays(45),
                ],
                // Topup round 2
                [
                    'type' => 'credit',
                    'amount' => 2_00_000.00,
                    'reference_type' => 'manual_topup',
                    'reference_id' => null,
                    'trip_id' => null,
                    'balance_after' => 5_80_000.00,
                    'balance_type' => 'cost_balance',
                    'receipt_file_url' => 'receipts/' . $customer->id . '/topup-002.pdf',
                    'created_at' => now()->subDays(30),
                ],
                // Trip debit (cash settlement for a completed trip)
                [
                    'type' => 'debit',
                    'amount' => 3_30_000.00,
                    'reference_type' => 'trip_debit',
                    'reference_id' => $firstTrip?->id,
                    'trip_id' => $firstTrip?->id,
                    'balance_after' => 2_50_000.00,
                    'balance_type' => 'cost_balance',
                    'receipt_file_url' => 'receipts/' . $customer->id . '/trip-' . ($firstTrip?->trip_ref ?? 'na') . '.pdf',
                    'created_at' => now()->subDays(15),
                ],
            ];

            // For credit-wallet customers, add a credit-draw / release pair
            if ($wallet->costing_type === \App\Enums\WalletCoastingType::Credit) {
                $entries[] = [
                    'type' => 'debit',
                    'amount' => 45_000.00,
                    'reference_type' => 'credit_draw',
                    'reference_id' => null,
                    'trip_id' => null,
                    'balance_after' => 0,
                    'balance_type' => 'available_credit',
                    'receipt_file_url' => null,
                    'created_at' => now()->subDays(7),
                ];
            }

            foreach ($entries as $entry) {
                CustomerWalletTransaction::insert([
                    'wallet_id' => $wallet->id,
                    'customer_id' => $customer->id,
                    'type' => $entry['type'],
                    'amount' => $entry['amount'],
                    'reference_type' => $entry['reference_type'],
                    'reference_id' => $entry['reference_id'],
                    'trip_id' => $entry['trip_id'],
                    'balance_after' => $entry['balance_after'],
                    'balance_type' => $entry['balance_type'],
                    'receipt_file_url' => $entry['receipt_file_url'],
                    'created_at' => $entry['created_at'],
                ]);
                $total++;
            }
        }

        $this->command->info("  WalletTransactionSeeder: {$total} transactions seeded.");
    }
}
