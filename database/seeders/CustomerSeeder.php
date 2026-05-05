<?php

namespace Database\Seeders;

use App\Enums\CompanyType;
use App\Enums\CustomerOnboardingStatus;
use App\Enums\UserStatus;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates 6 customers — one in each meaningful onboarding status —
 * plus a customer_admin user for each.
 *
 * Credentials for every customer_admin: password = "password"
 */
class CustomerSeeder extends Seeder
{
    /**
     * Returns the seeded customers so subsequent seeders can reference them.
     *
     * @return Customer[]
     */
    public function run(): array
    {
        $adminUser = User::where('email', 'admin@admin.com')->firstOrFail();
        $caRole = Role::where('name', 'customer_admin')->firstOrFail();
        $oeRole = Role::where('name', 'operations_executive')->firstOrFail();

        $definitions = $this->definitions();
        $customers = [];

        foreach ($definitions as $def) {
            $customer = Customer::firstOrCreate(
                ['email' => $def['email']],
                [
                    'first_name' => $def['first_name'],
                    'last_name' => $def['last_name'],
                    'company_name' => $def['company_name'],
                    'mobile' => $def['mobile'],
                    'company_type' => $def['company_type'],
                    'industry_type' => $def['industry_type'] ?? 'Export / Import',
                    'onboarding_status' => $def['onboarding_status'],
                    'is_active' => $def['is_active'] ?? true,
                    'sepio_company_id' => $def['sepio_company_id'] ?? null,
                    'gst_number' => $def['gst_number'] ?? null,
                    'pan_number' => $def['pan_number'] ?? null,
                    'iec_number' => $def['iec_number'] ?? null,
                    'cin_number' => $def['cin_number'] ?? null,
                    'billing_address' => $def['billing_address'] ?? null,
                    'billing_city' => $def['billing_city'] ?? null,
                    'billing_state' => $def['billing_state'] ?? null,
                    'billing_pincode' => $def['billing_pincode'] ?? null,
                    'billing_country' => 'India',
                    'primary_contact_name' => $def['first_name'] . ' ' . $def['last_name'],
                    'primary_contact_email' => $def['email'],
                    'primary_contact_mobile' => $def['mobile'],
                    'il_approved_by_id' => in_array(
                        $def['onboarding_status'],
                        [CustomerOnboardingStatus::IlApproved, CustomerOnboardingStatus::IlRejected,
                            CustomerOnboardingStatus::IlParked, CustomerOnboardingStatus::Completed,
                            CustomerOnboardingStatus::MfgRejected]
                    ) ? $adminUser->id : null,
                    'il_approved_at' => in_array(
                        $def['onboarding_status'],
                        [CustomerOnboardingStatus::IlApproved, CustomerOnboardingStatus::IlRejected,
                            CustomerOnboardingStatus::IlParked, CustomerOnboardingStatus::Completed,
                            CustomerOnboardingStatus::MfgRejected]
                    ) ? now()->subDays(rand(1, 15)) : null,
                    'il_remarks' => $def['il_remarks'] ?? null,
                    'created_by_id' => $adminUser->id,
                ]
            );

            // Create the customer_admin user if not exists
            User::firstOrCreate(
                ['email' => 'user.' . $customer->id . '@' . strtolower(str_replace(' ', '', $def['company_name'])) . '.test'],
                [
                    'role_id' => $caRole->id,
                    'customer_id' => $customer->id,
                    'name' => $def['first_name'] . ' ' . $def['last_name'],
                    'mobile' => $def['mobile'],
                    'password' => Hash::make('password'),
                    'status' => UserStatus::Active,
                    'created_by_id' => $adminUser->id,
                ]
            );

            // ── Operations Executive user — only for customers that can have trips ──
            // (IlApproved and Completed — they have locations, ports, wallets)
            if (in_array($def['onboarding_status'], [
                CustomerOnboardingStatus::IlApproved,
                CustomerOnboardingStatus::Completed,
            ])) {
                $companySlug = strtolower(str_replace(' ', '', $def['company_name']));
                User::firstOrCreate(
                    ['email' => 'ops.' . $customer->id . '@' . $companySlug . '.test'],
                    [
                        'role_id' => $oeRole->id,
                        'customer_id' => $customer->id,
                        'name' => 'Operations Executive — ' . $customer->company_name,
                        'mobile' => '98' . str_pad($customer->id * 11, 8, '0', STR_PAD_LEFT),
                        'password' => Hash::make('password'),
                        'status' => UserStatus::Active,
                        'created_by_id' => $adminUser->id,
                    ]
                );
            }

            $customers[] = $customer->fresh();
        }

        $this->command->info('  CustomerSeeder: ' . count($customers) . ' customers seeded.');

        return $customers;
    }

    // ── Definitions ──────────────────────────────────────────────────────────

    private function definitions(): array
    {
        return [
            // 1. Pending — just registered, no profile filled
            [
                'first_name' => 'Ravi',
                'last_name' => 'Sharma',
                'company_name' => 'Sharma Exports Pvt Ltd',
                'email' => 'ravi.sharma@sharmaexports.test',
                'mobile' => '9876543201',
                'company_type' => CompanyType::PvtLtd,
                'onboarding_status' => CustomerOnboardingStatus::Pending,
            ],

            // 2. Submitted — profile complete, docs uploaded, awaiting IL review
            [
                'first_name' => 'Priya',
                'last_name' => 'Mehta',
                'company_name' => 'Mehta International LLP',
                'email' => 'priya.mehta@mehtaintl.test',
                'mobile' => '9876543202',
                'company_type' => CompanyType::Llp,
                'onboarding_status' => CustomerOnboardingStatus::Submitted,
                'gst_number' => '27AABCM1234A1Z5',
                'pan_number' => 'AABCM1234A',
                'iec_number' => 'IEC0001002',
                'billing_address' => '12, Commerce House, Nariman Point',
                'billing_city' => 'Mumbai',
                'billing_state' => 'Maharashtra',
                'billing_pincode' => '400021',
            ],

            // 3. IL Parked — needs more info
            [
                'first_name' => 'Arjun',
                'last_name' => 'Patel',
                'company_name' => 'Patel Traders',
                'email' => 'arjun.patel@pateltraders.test',
                'mobile' => '9876543203',
                'company_type' => CompanyType::Proprietorship,
                'onboarding_status' => CustomerOnboardingStatus::IlParked,
                'gst_number' => '24ABCPP1234B1ZV',
                'pan_number' => 'ABCPP1234B',
                'iec_number' => 'IEC0001003',
                'billing_address' => 'Plot 5, GIDC Estate, Vatva',
                'billing_city' => 'Ahmedabad',
                'billing_state' => 'Gujarat',
                'billing_pincode' => '382445',
                'il_remarks' => 'GST certificate is blurry. Please re-upload a clear copy.',
            ],

            // 4. IL Approved — approved by IL, Sepio onboarding triggered
            [
                'first_name' => 'Sunita',
                'last_name' => 'Rao',
                'company_name' => 'Rao Global Trade Pvt Ltd',
                'email' => 'sunita.rao@raoglobal.test',
                'mobile' => '9876543204',
                'company_type' => CompanyType::PvtLtd,
                'onboarding_status' => CustomerOnboardingStatus::IlApproved,
                'sepio_company_id' => 'SPC10042',
                'gst_number' => '29AABCR5678C1Z3',
                'pan_number' => 'AABCR5678C',
                'iec_number' => 'IEC0001004',
                'billing_address' => '7th Floor, UB City, Vittal Mallya Road',
                'billing_city' => 'Bengaluru',
                'billing_state' => 'Karnataka',
                'billing_pincode' => '560001',
                'il_remarks' => 'All documents verified. Approved.',
            ],

            // 5. Completed — fully onboarded, can place orders
            [
                'first_name' => 'Kiran',
                'last_name' => 'Verma',
                'company_name' => 'Verma Logistics Solutions',
                'email' => 'kiran.verma@vermalogistics.test',
                'mobile' => '9876543205',
                'company_type' => CompanyType::Partnership,
                'onboarding_status' => CustomerOnboardingStatus::Completed,
                'sepio_company_id' => 'SPC10078',
                'gst_number' => '07AABCV9012D1Z1',
                'pan_number' => 'AABCV9012D',
                'iec_number' => 'IEC0001005',
                'billing_address' => 'B-12, Connaught Place',
                'billing_city' => 'New Delhi',
                'billing_state' => 'Delhi',
                'billing_pincode' => '110001',
                'il_remarks' => 'Full verification passed.',
            ],

            // 6. Another Completed customer — for multi-tenant testing
            [
                'first_name' => 'Meena',
                'last_name' => 'Iyer',
                'company_name' => 'Iyer Impex Pvt Ltd',
                'email' => 'meena.iyer@iyerimpex.test',
                'mobile' => '9876543206',
                'company_type' => CompanyType::PvtLtd,
                'onboarding_status' => CustomerOnboardingStatus::Completed,
                'sepio_company_id' => 'SPC10099',
                'gst_number' => '33AABCI3456E1Z8',
                'pan_number' => 'AABCI3456E',
                'iec_number' => 'IEC0001006',
                'billing_address' => '22, Anna Salai',
                'billing_city' => 'Chennai',
                'billing_state' => 'Tamil Nadu',
                'billing_pincode' => '600002',
                'il_remarks' => 'Documents and IEC verified.',
            ],
        ];
    }
}
