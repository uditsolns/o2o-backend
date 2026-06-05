<?php

namespace Database\Seeders;

use App\Enums\TripTransportationMode;
use App\Enums\TripType;
use App\Models\Customer;
use App\Models\TripPricingRule;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds trip_pricing_rules (added in migration 2026_05_28_104258).
 *
 * Two layers:
 *   1. GLOBAL default rules  (customer_id = NULL)  — fallback pricing
 *   2. PER-CUSTOMER rules     (customer_id = <id>) — overrides for the two completed tenants
 *
 * Unique constraint: (customer_id, trip_type, transport_mode)
 * The per-customer rules deliberately override the global rates for the same combo.
 */
class TripPricingRuleSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@admin.com')->firstOrFail();

        $count = 0;

        // ── 1. Global defaults ─────────────────────────────────────────────
        foreach ($this->globalDefinitions() as $def) {
            $exists = TripPricingRule::whereNull('customer_id')
                ->where('trip_type', $def['trip_type'])
                ->where('transport_mode', $def['transport_mode'])
                ->exists();
            if ($exists) continue;

            TripPricingRule::create(array_merge($def, [
                'customer_id' => null,
                'is_active' => true,
                'created_by_id' => $admin->id,
            ]));
            $count++;
        }

        // ── 2. Per-customer overrides (for Completed customers) ────────────
        $completedCustomers = Customer::where('onboarding_status', 'completed')->get();
        foreach ($completedCustomers as $customer) {
            foreach ($this->perCustomerDefinitions($customer) as $def) {
                $exists = TripPricingRule::where('customer_id', $customer->id)
                    ->where('trip_type', $def['trip_type'])
                    ->where('transport_mode', $def['transport_mode'])
                    ->exists();
                if ($exists) continue;

                TripPricingRule::create(array_merge($def, [
                    'customer_id' => $customer->id,
                    'is_active' => true,
                    'created_by_id' => $admin->id,
                ]));
                $count++;
            }
        }

        $this->command->info("  TripPricingRuleSeeder: {$count} pricing rules seeded.");
    }

    private function globalDefinitions(): array
    {
        // All 9 combinations of trip_type × transport_mode at default rate
        return [
            // IMPORT
            ['trip_type' => TripType::Import->value, 'transport_mode' => TripTransportationMode::Road->value,       'price_per_trip' => 4_500.00],
            ['trip_type' => TripType::Import->value, 'transport_mode' => TripTransportationMode::Sea->value,        'price_per_trip' => 18_000.00],
            ['trip_type' => TripType::Import->value, 'transport_mode' => TripTransportationMode::Multimodal->value, 'price_per_trip' => 22_500.00],

            // EXPORT
            ['trip_type' => TripType::Export->value, 'transport_mode' => TripTransportationMode::Road->value,       'price_per_trip' => 4_800.00],
            ['trip_type' => TripType::Export->value, 'transport_mode' => TripTransportationMode::Sea->value,        'price_per_trip' => 19_500.00],
            ['trip_type' => TripType::Export->value, 'transport_mode' => TripTransportationMode::Multimodal->value, 'price_per_trip' => 24_000.00],

            // DOMESTIC
            ['trip_type' => TripType::Domestic->value, 'transport_mode' => TripTransportationMode::Road->value,       'price_per_trip' => 3_500.00],
            ['trip_type' => TripType::Domestic->value, 'transport_mode' => TripTransportationMode::Sea->value,        'price_per_trip' => 12_000.00],
            ['trip_type' => TripType::Domestic->value, 'transport_mode' => TripTransportationMode::Multimodal->value, 'price_per_trip' => 15_000.00],
        ];
    }

    /**
     * Per-customer rate overrides — apply a customer-specific multiplier
     * based on the customer's id (purely demonstrative; the real system
     * would pull these from a contract).
     */
    private function perCustomerDefinitions(Customer $customer): array
    {
        // Verma → 12% discount, Iyer → 8% discount
        $multiplier = match ($customer->email) {
            'kiran.verma@vermalogistics.test' => 0.88,
            'meena.iyer@iyerimpex.test'       => 0.92,
            default                           => 1.0,
        };

        $rules = [
            // IMPORT
            ['trip_type' => TripType::Import->value, 'transport_mode' => TripTransportationMode::Road->value,       'price_per_trip' => 4_500.00],
            ['trip_type' => TripType::Import->value, 'transport_mode' => TripTransportationMode::Sea->value,        'price_per_trip' => 18_000.00],
            ['trip_type' => TripType::Import->value, 'transport_mode' => TripTransportationMode::Multimodal->value, 'price_per_trip' => 22_500.00],

            // EXPORT
            ['trip_type' => TripType::Export->value, 'transport_mode' => TripTransportationMode::Road->value,       'price_per_trip' => 4_800.00],
            ['trip_type' => TripType::Export->value, 'transport_mode' => TripTransportationMode::Sea->value,        'price_per_trip' => 19_500.00],
            ['trip_type' => TripType::Export->value, 'transport_mode' => TripTransportationMode::Multimodal->value, 'price_per_trip' => 24_000.00],

            // DOMESTIC
            ['trip_type' => TripType::Domestic->value, 'transport_mode' => TripTransportationMode::Road->value,       'price_per_trip' => 3_500.00],
            ['trip_type' => TripType::Domestic->value, 'transport_mode' => TripTransportationMode::Sea->value,        'price_per_trip' => 12_000.00],
            ['trip_type' => TripType::Domestic->value, 'transport_mode' => TripTransportationMode::Multimodal->value, 'price_per_trip' => 15_000.00],
        ];

        return array_map(fn($r) => [
            'trip_type' => $r['trip_type'],
            'transport_mode' => $r['transport_mode'],
            'price_per_trip' => round($r['price_per_trip'] * $multiplier, 2),
        ], $rules);
    }
}
