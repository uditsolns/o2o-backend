<?php

namespace Database\Seeders;

use App\Enums\SepioStatus;
use App\Models\Customer;
use App\Models\CustomerSepioHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds customer_sepio_history rows.
 *
 * The Sepio integration is OPTIONAL (per migration 2026_05_25_120649).
 *
 *   - sepio_enabled = false  →  no history rows (status stays Disabled)
 *   - sepio_enabled = true   →  history walks through the lifecycle
 *       pending  → registered  → docs_uploaded  → verification_pending  → verified | rejected
 */
class CustomerSepioHistorySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@admin.com')->first();
        if (!$admin) {
            $this->command->warn('  Skipping CustomerSepioHistorySeeder — admin user not found.');
            return;
        }

        $sepioCustomers = Customer::where('sepio_enabled', true)
            ->orderBy('id')
            ->get();

        $count = 0;

        foreach ($sepioCustomers as $customer) {
            if (CustomerSepioHistory::where('customer_id', $customer->id)->exists()) {
                continue;
            }

            $customerUser = User::where('customer_id', $customer->id)->first();
            $customerActorId = $customerUser?->id ?? $admin->id;
            $rows = $this->historyRowsFor($customer, $admin->id, $customerActorId);

            foreach ($rows as $row) {
                CustomerSepioHistory::insert([
                    'customer_id' => $customer->id,
                    'from_status' => $row['from_status'],
                    'to_status' => $row['to_status'],
                    'triggered_by_type' => $row['triggered_by_type'],
                    'triggered_by_id' => $row['triggered_by_id'],
                    'remarks' => $row['remarks'],
                    'rejected_documents' => isset($row['rejected_documents'])
                        ? json_encode($row['rejected_documents'])
                        : null,
                    'created_at' => $row['created_at'],
                ]);
                $count++;
            }
        }

        $this->command->info("  CustomerSepioHistorySeeder: {$count} history rows seeded.");
    }

    /**
     * Build the lifecycle history for a single customer based on its current sepio_status.
     *
     * @return array<int, array<string, mixed>>
     */
    private function historyRowsFor(Customer $customer, int $adminId, int $customerId): array
    {
        $current = $customer->sepio_status instanceof SepioStatus
            ? $customer->sepio_status->value
            : $customer->sepio_status;

        $baseTime = ($customer->created_at ?? now()->subDays(60))->copy()->addDays(2);
        $step = fn(int $days) => (clone $baseTime)->addDays($days);

        // 1. Sepio integration enabled (initial `null → pending` is implicit
        //    when sepio_enabled is flipped on; the DB column is nullable)
        $rows = [
            [
                'from_status' => null,
                'to_status' => SepioStatus::Pending->value,
                'triggered_by_type' => 'customer',
                'triggered_by_id' => $customerId,
                'remarks' => 'Sepio integration enabled by customer. Awaiting IL approval.',
                'created_at' => $step(0),
            ],
        ];

        // 2. IL approval → Sepio registration request
        $rows[] = [
            'from_status' => SepioStatus::Pending->value,
            'to_status' => SepioStatus::Registered->value,
            'triggered_by_type' => 'platform',
            'triggered_by_id' => $adminId,
            'remarks' => 'Customer registered with Sepio. Awaiting KYC document upload.',
            'created_at' => $step(2),
        ];

        if (in_array($current, [
            SepioStatus::DocsUploaded->value,
            SepioStatus::VerificationPending->value,
            SepioStatus::Verified->value,
            SepioStatus::Rejected->value,
        ], true)) {
            $rows[] = [
                'from_status' => SepioStatus::Registered->value,
                'to_status' => SepioStatus::DocsUploaded->value,
                'triggered_by_type' => 'customer',
                'triggered_by_id' => $customerId,
                'remarks' => 'All required KYC documents uploaded (PAN, IEC, GST, Certificate of Registration, Self-stuffing declaration).',
                'created_at' => $step(4),
            ];
        }

        if (in_array($current, [
            SepioStatus::VerificationPending->value,
            SepioStatus::Verified->value,
            SepioStatus::Rejected->value,
        ], true)) {
            $rows[] = [
                'from_status' => SepioStatus::DocsUploaded->value,
                'to_status' => SepioStatus::VerificationPending->value,
                'triggered_by_type' => 'system',
                'triggered_by_id' => null,
                'remarks' => 'Documents forwarded to Sepio for verification.',
                'created_at' => $step(6),
            ];
        }

        // 3. Final state
        if ($current === SepioStatus::Verified->value) {
            $rows[] = [
                'from_status' => SepioStatus::VerificationPending->value,
                'to_status' => SepioStatus::Verified->value,
                'triggered_by_type' => 'platform',
                'triggered_by_id' => $adminId,
                'remarks' => 'Sepio verification complete. Customer is now ready to place seal orders.',
                'created_at' => $step(10),
            ];
        } elseif ($current === SepioStatus::Rejected->value) {
            $rows[] = [
                'from_status' => SepioStatus::VerificationPending->value,
                'to_status' => SepioStatus::Rejected->value,
                'triggered_by_type' => 'platform',
                'triggered_by_id' => $adminId,
                'remarks' => 'Sepio rejected the submitted documents. Customer must re-upload.',
                'rejected_documents' => ['self_stuffing_cert', 'cha_auth_letter'],
                'created_at' => $step(10),
            ];
        }

        return $rows;
    }
}
