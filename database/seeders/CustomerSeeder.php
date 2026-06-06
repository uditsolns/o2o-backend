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
 * plus a customer_admin + (where applicable) operations_executive user for each.
 *
 * Demonstrates the optional Sepio integration lifecycle:
 *   - sepio_enabled = false  →  sepio_status = null (Disabled)
 *   - sepio_enabled = true   →  sepio_status progresses through SepioStatus cases
 *
 * This seeder is fully self-contained and synthetic. The Sepio-enabled
 * customers use placeholder sepio_company_id values (SPC10xxx) and
 * sepio_status=verification_pending so the Sepio-aware code paths can
 * still be exercised end-to-end (status checks, read-only Sepio KYC
 * inspection, etc.) without any real Sepio integration. The frontend
 * can be developed against the realistic shape of the data without ever
 * triggering a real Sepio API call from local.
 *
 * For data with real sepio_company_id values + auth tokens, see
 * LiveSepioSeeder (loads database/o2o.sql which the operator uploads
 * at seed time).
 *
 * Insurance (IL) policy fields live on the customer now (moved from customer_wallets).
 *
 * Credentials for every customer_admin/ops user: password = "password"
 *
 * SECURITY NOTE: real `sepio_token` (JWT) and `sepio_credentials` (encrypted
 * {email, password}) are NEVER hardcoded here. The local seeder sets these
 * to null. Live credentials are loaded separately via LiveSepioSeeder
 * (which reads database/o2o.sql — file not committed to the repo).
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
            $onboardingStatus = $def['onboarding_status'];
            $ilDecision = in_array(
                $onboardingStatus,
                [
                    CustomerOnboardingStatus::IlApproved->value,
                    CustomerOnboardingStatus::IlRejected->value,
                    CustomerOnboardingStatus::IlParked->value,
                    CustomerOnboardingStatus::Completed->value,
                ],
                true
            );

            $customer = Customer::firstOrCreate(
                ['email' => $def['email']],
                [
                    'first_name' => $def['first_name'],
                    'last_name' => $def['last_name'],
                    'company_name' => $def['company_name'],
                    'mobile' => $def['mobile'],
                    'company_type' => $def['company_type'],
                    'industry_type' => $def['industry_type'] ?? 'Export / Import',
                    'onboarding_status' => $onboardingStatus,

                    // ── Sepio integration (optional) ─────────────────────────
                    'sepio_enabled' => $def['sepio_enabled'] ?? false,
                    // sepio_status is nullable in the DB; null means Sepio is not active.
                    'sepio_status' => $def['sepio_enabled'] ?? false
                        ? ($def['sepio_status'] ?? null)
                        : null,
                    'sepio_company_id' => $def['sepio_company_id'] ?? null,
                    'sepio_token_expires_at' => $def['sepio_token_expires_at'] ?? null,

                    // ── IL policy fields (moved from customer_wallets) ───────
                    'il_policy_number' => $def['il_policy_number'] ?? null,
                    'il_policy_expiry' => $def['il_policy_expiry'] ?? null,
                    'sum_insured' => $def['sum_insured'] ?? null,
                    'gwp' => $def['gwp'] ?? null,

                    // ── Tax / compliance identifiers ─────────────────────────
                    'gst_number' => $def['gst_number'] ?? null,
                    'iec_number' => $def['iec_number'] ?? null,

                    // ── Billing address (used by CustomerLocationSeeder too) ─
                    'billing_address' => $def['billing_address'] ?? null,
                    'billing_landmark' => $def['billing_landmark'] ?? null,
                    'billing_city' => $def['billing_city'] ?? null,
                    'billing_state' => $def['billing_state'] ?? null,
                    'billing_pincode' => $def['billing_pincode'] ?? null,
                    'billing_country' => 'India',

                    // ── Primary / alternate contact ──────────────────────────
                    'primary_contact_name' => $def['first_name'] . ' ' . $def['last_name'],
                    'primary_contact_email' => $def['email'],
                    'primary_contact_mobile' => $def['mobile'],
                    'alternate_contact_name' => $def['alternate_contact_name'] ?? null,
                    'alternate_contact_phone' => $def['alternate_contact_phone'] ?? null,
                    'alternate_contact_email' => $def['alternate_contact_email'] ?? null,

                    'is_active' => $def['is_active'] ?? true,

                    // ── IL decision fields ───────────────────────────────────
                    'il_approved_by_id' => $ilDecision ? $adminUser->id : null,
                    'il_approved_at' => $ilDecision ? now()->subDays(rand(1, 15)) : null,
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
            if (in_array($onboardingStatus, [
                CustomerOnboardingStatus::IlApproved->value,
                CustomerOnboardingStatus::Completed->value,
            ], true)) {
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
            // 1. Pending — just registered, no profile filled, no Sepio
            [
                'first_name' => 'Ravi',
                'last_name' => 'Sharma',
                'company_name' => 'Sharma Exports Pvt Ltd',
                'email' => 'ravi.sharma@sharmaexports.test',
                'mobile' => '9876543201',
                'company_type' => CompanyType::PvtLtd,
                'onboarding_status' => CustomerOnboardingStatus::Pending->value,
                'sepio_enabled' => false,
            ],

            // 2. Submitted — profile complete, docs uploaded, awaiting IL review, no Sepio
            [
                'first_name' => 'Priya',
                'last_name' => 'Mehta',
                'company_name' => 'Mehta International LLP',
                'email' => 'priya.mehta@mehtaintl.test',
                'mobile' => '9876543202',
                'company_type' => CompanyType::Llp,
                'onboarding_status' => CustomerOnboardingStatus::Submitted->value,
                'sepio_enabled' => false,
                'gst_number' => '27AABCM1234A1Z5',
                'iec_number' => 'IEC0001002',
                'billing_address' => '12, Commerce House, Nariman Point',
                'billing_city' => 'Mumbai',
                'billing_state' => 'Maharashtra',
                'billing_pincode' => '400021',
            ],

            // 3. IL Parked — needs more info, no Sepio
            [
                'first_name' => 'Arjun',
                'last_name' => 'Patel',
                'company_name' => 'Patel Traders',
                'email' => 'arjun.patel@pateltraders.test',
                'mobile' => '9876543203',
                'company_type' => CompanyType::Proprietorship,
                'onboarding_status' => CustomerOnboardingStatus::IlParked->value,
                'sepio_enabled' => false,
                'gst_number' => '24ABCPP1234B1ZV',
                'iec_number' => 'IEC0001003',
                'billing_address' => 'Plot 5, GIDC Estate, Vatva',
                'billing_city' => 'Ahmedabad',
                'billing_state' => 'Gujarat',
                'billing_pincode' => '382445',
                'il_remarks' => 'GST certificate is blurry. Please re-upload a clear copy.',
            ],

            // 4. IL Approved — Sepio registration submitted, awaiting
            // Sepio-side verification. Synthetic sepio_company_id; for
            // real testing-environment ids use the LiveSepioSeeder profile.
            [
                'first_name' => 'Sunita',
                'last_name' => 'Rao',
                'company_name' => 'Rao Global Trade Pvt Ltd',
                'email' => 'sunita.rao@raoglobal.test',
                'mobile' => '9876543204',
                'company_type' => CompanyType::PvtLtd,
                'onboarding_status' => CustomerOnboardingStatus::IlApproved->value,
                'sepio_enabled' => true,
                'sepio_status' => 'verification_pending',
                'sepio_company_id' => 'SPC10042',
                // sepio_token / sepio_credentials intentionally null — see
                // SECURITY NOTE in the class docblock.
                'gst_number' => '29AABCR5678C1Z3',
                'iec_number' => 'IEC0001004',
                'il_policy_number' => 'ILPOL-' . str_pad(10042, 5, '0', STR_PAD_LEFT),
                'il_policy_expiry' => now()->addYear(),
                'sum_insured' => 60_00_000.00,
                'gwp' => 18_000.00,
                'billing_address' => '7th Floor, UB City, Vittal Mallya Road',
                'billing_city' => 'Bengaluru',
                'billing_state' => 'Karnataka',
                'billing_pincode' => '560001',
                'il_remarks' => 'All documents verified. Approved. Awaiting Sepio KYC.',
            ],

            // 5. Completed (no Sepio) — fully onboarded, can place orders (Sepio not enabled)
            [
                'first_name' => 'Kiran',
                'last_name' => 'Verma',
                'company_name' => 'Verma Logistics Solutions',
                'email' => 'kiran.verma@vermalogistics.test',
                'mobile' => '9876543205',
                'company_type' => CompanyType::Partnership,
                'onboarding_status' => CustomerOnboardingStatus::Completed->value,
                'sepio_enabled' => false,
                'gst_number' => '07AABCV9012D1Z1',
                'iec_number' => 'IEC0001005',
                'il_policy_number' => 'ILPOL-' . str_pad(10078, 5, '0', STR_PAD_LEFT),
                'il_policy_expiry' => now()->addYear(),
                'sum_insured' => 75_00_000.00,
                'gwp' => 22_000.00,
                'billing_address' => 'B-12, Connaught Place',
                'billing_city' => 'New Delhi',
                'billing_state' => 'Delhi',
                'billing_pincode' => '110001',
                'il_remarks' => 'Full verification passed.',
            ],

            // 6. Completed (Sepio registered) — fully onboarded, Sepio
            // company registration submitted, awaiting verification on the
            // Sepio side. Synthetic sepio_company_id.
            [
                'first_name' => 'Meena',
                'last_name' => 'Iyer',
                'company_name' => 'Iyer Impex Pvt Ltd',
                'email' => 'meena.iyer@iyerimpex.test',
                'mobile' => '9876543206',
                'company_type' => CompanyType::PvtLtd,
                'onboarding_status' => CustomerOnboardingStatus::Completed->value,
                'sepio_enabled' => true,
                'sepio_status' => 'verification_pending',
                'sepio_company_id' => 'SPC10099',
                // sepio_token / sepio_credentials intentionally null — see
                // SECURITY NOTE in the class docblock.
                'gst_number' => '33AABCI3456E1Z8',
                'iec_number' => 'IEC0001006',
                'il_policy_number' => 'ILPOL-' . str_pad(10099, 5, '0', STR_PAD_LEFT),
                'il_policy_expiry' => now()->addYear(),
                'sum_insured' => 90_00_000.00,
                'gwp' => 28_000.00,
                'billing_address' => '22, Anna Salai',
                'billing_city' => 'Chennai',
                'billing_state' => 'Tamil Nadu',
                'billing_pincode' => '600002',
                'il_remarks' => 'Documents and IEC verified. Sepio registration in progress.',
            ],
        ];
    }
}
