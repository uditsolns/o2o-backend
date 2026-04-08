<?php

namespace Database\Seeders;

use App\Enums\CustomerOnboardingStatus;
use App\Enums\WalletCoastingType;
use App\Models\Customer;
use App\Models\CustomerWallet;
use App\Models\SealPricingTier;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds CustomerWallet + SealPricingTiers for completed/IL-approved customers.
 *
 * Two costing types to exercise both paths:
 *   - Customer 1 (Rao Global)    → Cash, with advance balance pre-loaded
 *   - Customer 2 (Verma Logistics) → Credit with capping
 *   - Customer 3 (Iyer Impex)   → Cash, no balance (edge case)
 */
class CustomerWalletSeeder extends Seeder
{
    /**
     * @return array<int, CustomerWallet>  keyed by customer_id
     */
    public function run(): array
    {
        $admin = User::where('email', 'admin@admin.com')->firstOrFail();

        $customers = Customer::whereIn('onboarding_status', [
            CustomerOnboardingStatus::IlApproved->value,
            CustomerOnboardingStatus::Completed->value,
        ])->get();

        $walletDefs = $this->walletDefinitions();
        $result = [];
        $wCount = 0;
        $tCount = 0;

        foreach ($customers as $index => $customer) {
            if ($customer->wallet()->exists()) {
                $result[$customer->id] = $customer->wallet;
                continue;
            }

            $def = $walletDefs[$index % count($walletDefs)];

            $wallet = CustomerWallet::create([
                'customer_id' => $customer->id,
                'il_policy_number' => 'ILPOL-' . str_pad($customer->id, 5, '0', STR_PAD_LEFT),
                'il_policy_expiry' => now()->addYear(),
                'sum_insured' => $def['sum_insured'],
                'gwp' => $def['gwp'],
                'costing_type' => $def['costing_type'],
                'credit_period' => $def['credit_period'] ?? null,
                'credit_capping' => $def['credit_capping'] ?? null,
                'credit_used' => $def['credit_used'] ?? 0,
                'freight_rate_per_seal' => $def['freight_rate_per_seal'],
                'cost_balance' => $def['cost_balance'],
                'created_by_id' => $admin->id,
            ]);

            $wCount++;

            // Pricing tiers
            foreach ($def['tiers'] as $tier) {
                SealPricingTier::firstOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'min_quantity' => $tier['min_quantity'],
                    ],
                    [
                        'max_quantity' => $tier['max_quantity'],
                        'price_per_seal' => $tier['price_per_seal'],
                        'is_active' => true,
                        'created_by_id' => $admin->id,
                    ]
                );
                $tCount++;
            }

            $result[$customer->id] = $wallet;
        }

        $this->command->info("  CustomerWalletSeeder: {$wCount} wallets, {$tCount} pricing tiers seeded.");

        return $result;
    }

    // ── Wallet definitions (cycled across customers) ──────────────────────────

    private function walletDefinitions(): array
    {
        return [
            // Cash wallet with healthy balance
            [
                'costing_type' => WalletCoastingType::Cash,
                'sum_insured' => 50_00_000.00,
                'gwp' => 15_000.00,
                'freight_rate_per_seal' => 12.50,
                'cost_balance' => 2_50_000.00,
                'credit_period' => null,
                'credit_capping' => null,
                'credit_used' => 0,
                'tiers' => [
                    ['min_quantity' => 20, 'max_quantity' => 99, 'price_per_seal' => 180.00],
                    ['min_quantity' => 100, 'max_quantity' => 499, 'price_per_seal' => 165.00],
                    ['min_quantity' => 500, 'max_quantity' => null, 'price_per_seal' => 150.00],
                ],
            ],
            // Credit wallet
            [
                'costing_type' => WalletCoastingType::Credit,
                'sum_insured' => 75_00_000.00,
                'gwp' => 20_000.00,
                'freight_rate_per_seal' => 10.00,
                'cost_balance' => 0,
                'credit_period' => 30,
                'credit_capping' => 5_00_000.00,
                'credit_used' => 45_000.00,
                'tiers' => [
                    ['min_quantity' => 20, 'max_quantity' => 199, 'price_per_seal' => 170.00],
                    ['min_quantity' => 200, 'max_quantity' => null, 'price_per_seal' => 155.00],
                ],
            ],
            // Cash wallet — zero balance (edge case for payment tests)
            [
                'costing_type' => WalletCoastingType::Cash,
                'sum_insured' => 30_00_000.00,
                'gwp' => 8_000.00,
                'freight_rate_per_seal' => 15.00,
                'cost_balance' => 0,
                'credit_period' => null,
                'credit_capping' => null,
                'credit_used' => 0,
                'tiers' => [
                    ['min_quantity' => 20, 'max_quantity' => 499, 'price_per_seal' => 175.00],
                    ['min_quantity' => 500, 'max_quantity' => null, 'price_per_seal' => 160.00],
                ],
            ],
        ];
    }
}
