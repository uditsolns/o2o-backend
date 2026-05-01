<?php

namespace Database\Seeders;

use App\Enums\CustomerOnboardingStatus;
use App\Models\Customer;
use App\Models\CustomerLocation;
use App\Models\User;
use Illuminate\Database\Seeder;

class CustomerLocationSeeder extends Seeder
{
    /**
     * @return array<int, CustomerLocation[]>
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
                    ['customer_id' => $customer->id, 'name' => $def['name']],
                    array_merge($def, [
                        'customer_id' => $customer->id,
                        'created_by_id' => $user->id,
                    ])
                );
                $locations[] = $loc;
                $total++;
            }

            $result[$customer->id] = $locations;
        }

        $this->command->info("  CustomerLocationSeeder: {$total} locations seeded.");

        return $result;
    }

    // ── Per-customer location definitions ─────────────────────────────────────

    private function definitions(Customer $customer): array
    {
        return match ($customer->email) {

            // ── Verma Logistics Solutions (Delhi) ────────────────────────────
            'kiran.verma@vermalogistics.test' => [
                [
                    'name' => 'Verma Logistics — Mayapuri HO',
                    'gst_number' => $customer->gst_number,
                    'address' => 'Plot 14, Phase II, Mayapuri Industrial Area',
                    'landmark' => 'Near Mayapuri Metro Station',
                    'city' => 'New Delhi',
                    'state' => 'Delhi',
                    'pincode' => '110064',
                    'country' => 'India',
                    'contact_person' => 'Kiran Verma',
                    'contact_number' => '9876543205',
                    'lat' => 28.6358,
                    'lng' => 77.1022,
                    'sepio_billing_address_id' => 'ADDRBL_VL_001',
                    'sepio_shipping_address_id' => 'ADDRSH_VL_001',
                    'is_active' => true,
                ],
                [
                    'name' => 'Verma Logistics — NSEZ Noida Warehouse',
                    'gst_number' => $customer->gst_number,
                    'address' => 'Unit B-12, Noida Special Economic Zone',
                    'landmark' => 'Near NSEZ Gate 2',
                    'city' => 'Noida',
                    'state' => 'Uttar Pradesh',
                    'pincode' => '201305',
                    'country' => 'India',
                    'contact_person' => 'Warehouse Manager',
                    'contact_number' => '9871100101',
                    'lat' => 28.5706,
                    'lng' => 77.3522,
                    'sepio_billing_address_id' => 'ADDRBL_VL_002',
                    'sepio_shipping_address_id' => 'ADDRSH_VL_002',
                    'is_active' => true,
                ],
                [
                    'name' => 'Verma Logistics — Gurgaon Branch',
                    'gst_number' => null,
                    'address' => '903, Unitech Cyber Park, Sector 39',
                    'landmark' => 'Near NH-48',
                    'city' => 'Gurgaon',
                    'state' => 'Haryana',
                    'pincode' => '122001',
                    'country' => 'India',
                    'contact_person' => 'Branch Manager',
                    'contact_number' => '9810099210',
                    'lat' => 28.4595,
                    'lng' => 77.0266,
                    'sepio_billing_address_id' => 'ADDRBL_VL_003',
                    'sepio_shipping_address_id' => 'ADDRSH_VL_003',
                    'is_active' => true,
                ],
            ],

            // ── Iyer Impex Pvt Ltd (Chennai) ─────────────────────────────────
            'meena.iyer@iyerimpex.test' => [
                [
                    'name' => 'Iyer Impex — Ambattur Factory',
                    'gst_number' => $customer->gst_number,
                    'address' => 'Survey No. 45, Ambattur Industrial Estate',
                    'landmark' => 'Near Ambattur OT Bus Stop',
                    'city' => 'Chennai',
                    'state' => 'Tamil Nadu',
                    'pincode' => '600058',
                    'country' => 'India',
                    'contact_person' => 'Meena Iyer',
                    'contact_number' => '9876543206',
                    'lat' => 13.1156,
                    'lng' => 80.1551,
                    'sepio_billing_address_id' => 'ADDRBL_II_001',
                    'sepio_shipping_address_id' => 'ADDRSH_II_001',
                    'is_active' => true,
                ],
                [
                    'name' => 'Iyer Impex — Bengaluru Warehouse',
                    'gst_number' => $customer->gst_number,
                    'address' => 'Shed 7, Electronic City Phase 2',
                    'landmark' => 'Near Infosys Campus Gate',
                    'city' => 'Bengaluru',
                    'state' => 'Karnataka',
                    'pincode' => '560100',
                    'country' => 'India',
                    'contact_person' => 'Suresh Babu',
                    'contact_number' => '9845011234',
                    'lat' => 12.8441,
                    'lng' => 77.6689,
                    'sepio_billing_address_id' => 'ADDRBL_II_002',
                    'sepio_shipping_address_id' => 'ADDRSH_II_002',
                    'is_active' => true,
                ],
                [
                    'name' => 'Iyer Impex — Chennai Port Office',
                    'gst_number' => null,
                    'address' => '22, Rajaji Salai, George Town',
                    'landmark' => 'Opposite Chennai Port Trust Gate 3',
                    'city' => 'Chennai',
                    'state' => 'Tamil Nadu',
                    'pincode' => '600001',
                    'country' => 'India',
                    'contact_person' => 'CHA Agent',
                    'contact_number' => '9840099100',
                    'lat' => 13.0836,
                    'lng' => 80.2850,
                    'sepio_billing_address_id' => 'ADDRBL_II_003',
                    'sepio_shipping_address_id' => 'ADDRSH_II_003',
                    'is_active' => true,
                ],
            ],

            // ── Rao Global Trade Pvt Ltd (Bengaluru — IL Approved) ───────────
            'sunita.rao@raoglobal.test' => [
                [
                    'name' => 'Rao Global — UB City Office',
                    'gst_number' => $customer->gst_number,
                    'address' => '7th Floor, UB City, Vittal Mallya Road',
                    'landmark' => 'Near Cubbon Park Metro',
                    'city' => 'Bengaluru',
                    'state' => 'Karnataka',
                    'pincode' => '560001',
                    'country' => 'India',
                    'contact_person' => 'Sunita Rao',
                    'contact_number' => '9876543204',
                    'lat' => 12.9716,
                    'lng' => 77.5946,
                    'sepio_billing_address_id' => 'ADDRBL_RG_001',
                    'sepio_shipping_address_id' => 'ADDRSH_RG_001',
                    'is_active' => true,
                ],
                [
                    'name' => 'Rao Global — Whitefield Warehouse',
                    'gst_number' => $customer->gst_number,
                    'address' => 'Plot 34, Whitefield Industrial Area, EPIP Zone',
                    'landmark' => 'Near ITPL Main Gate',
                    'city' => 'Bengaluru',
                    'state' => 'Karnataka',
                    'pincode' => '560066',
                    'country' => 'India',
                    'contact_person' => 'Logistics Head',
                    'contact_number' => '9845099200',
                    'lat' => 12.9780,
                    'lng' => 77.7480,
                    'sepio_billing_address_id' => 'ADDRBL_RG_002',
                    'sepio_shipping_address_id' => 'ADDRSH_RG_002',
                    'is_active' => true,
                ],
            ],

            // ── Default fallback ──────────────────────────────────────────────
            default => [
                [
                    'name' => $customer->company_name . ' — HO',
                    'gst_number' => $customer->gst_number,
                    'address' => $customer->billing_address ?? '100 Main Street',
                    'city' => $customer->billing_city ?? 'Mumbai',
                    'state' => $customer->billing_state ?? 'Maharashtra',
                    'pincode' => $customer->billing_pincode ?? '400001',
                    'country' => 'India',
                    'contact_person' => $customer->primary_contact_name,
                    'contact_number' => $customer->primary_contact_mobile,
                    'lat' => 19.0760,
                    'lng' => 72.8777,
                    'sepio_billing_address_id' => 'ADDRBL_' . $customer->id . '_001',
                    'sepio_shipping_address_id' => 'ADDRSH_' . $customer->id . '_001',
                    'is_active' => true,
                ],
            ],
        };
    }
}
