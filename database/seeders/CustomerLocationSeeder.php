<?php

namespace Database\Seeders;

use App\Enums\CustomerOnboardingStatus;
use App\Enums\LocationType;
use App\Models\Customer;
use App\Models\CustomerLocation;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds billing, shipping, and "both" type locations for completed customers.
 * Returns a map of customer_id → [locations] so downstream seeders can use them.
 */
class CustomerLocationSeeder extends Seeder
{
    /**
     * @return array<int, CustomerLocation[]>  keyed by customer_id
     */
    public function run(): array
    {
        $customers = Customer::whereIn('onboarding_status', [
            CustomerOnboardingStatus::IlApproved->value,
            CustomerOnboardingStatus::Completed->value,
        ])->get();

        $result = [];
        $total = 0;

        foreach ($customers as $customer) {
            $user = User::where('customer_id', $customer->id)->firstOrFail();
            $locations = [];

            foreach ($this->definitions($customer) as $def) {
                $loc = CustomerLocation::firstOrCreate(
                    [
                        'customer_id' => $customer->id,
                        'name' => $def['name'],
                    ],
                    [...$def, 'customer_id' => $customer->id, 'created_by_id' => $user->id]
                );
                $locations[] = $loc;
                $total++;
            }

            $result[$customer->id] = $locations;
        }

        $this->command->info("  CustomerLocationSeeder: {$total} locations seeded.");

        return $result;
    }

    // ── Definitions (3 locations per customer) ────────────────────────────────

    private function definitions(Customer $customer): array
    {
        // Use the customer's billing address as the "both" location so data is consistent
        return [
            [
                'name' => $customer->company_name . ' - HO',
                'gst_number' => $customer->gst_number,
                'address' => $customer->billing_address,
                'city' => $customer->billing_city,
                'state' => $customer->billing_state,
                'pincode' => $customer->billing_pincode,
                'country' => 'India',
                'contact_person' => $customer->primary_contact_name,
                'contact_number' => $customer->primary_contact_mobile,
                'lat' => fake()->latitude(8, 35),
                'lng' => fake()->longitude(68, 97),
                'is_active' => true,
                'sepio_billing_address_id' => 'ADDRBL' . $customer->id . '03',
                'sepio_shipping_address_id' => 'ADDRSH' . $customer->id . '03',
            ],
            [
                'name' => $customer->company_name . ' - Warehouse',
                'gst_number' => null,
                'address' => '45, Industrial Area Phase 2',
                'city' => $customer->billing_city,
                'state' => $customer->billing_state,
                'pincode' => $customer->billing_pincode,
                'country' => 'India',
                'contact_person' => 'Warehouse Manager',
                'contact_number' => '9800' . rand(100000, 999999),
                'lat' => fake()->latitude(8, 35),
                'lng' => fake()->longitude(68, 97),
                'is_active' => true,
                'sepio_billing_address_id' => 'ADDRBL' . $customer->id . '03',
                'sepio_shipping_address_id' => 'ADDRSH' . $customer->id . '03',
            ],
            [
                'name' => $customer->company_name . ' - Branch Office',
                'gst_number' => $customer->gst_number,
                'address' => '301, Trade Tower, MG Road',
                'city' => 'Pune',
                'state' => 'Maharashtra',
                'pincode' => '411001',
                'country' => 'India',
                'contact_person' => 'Branch Manager',
                'contact_number' => '9810' . rand(100000, 999999),
                'lat' => 18.5204,
                'lng' => 73.8567,
                'is_active' => true,
                'sepio_billing_address_id' => 'ADDRBL' . $customer->id . '03',
                'sepio_shipping_address_id' => 'ADDRSH' . $customer->id . '03',
            ],
        ];
    }
}
