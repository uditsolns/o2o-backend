<?php

namespace Database\Seeders;

use App\Enums\CustomerOnboardingStatus;
use App\Models\Customer;
use App\Models\CustomerOnboardingHistory;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds customer_onboarding_history rows that mirror each customer's progression
 * through the onboarding lifecycle.
 *
 * Triggered transitions (per OnboardingService):
 *   null        → pending        (customer self-registers, implicit)
 *   pending     → submitted      (customer submits profile for IL review)
 *   submitted   → il_approved    (IL platform approves)
 *   submitted   → il_rejected    (IL platform rejects)
 *   submitted   → il_parked      (IL platform parks for more info)
 *   il_rejected → pending        (customer acknowledges rejection)
 *   il_approved → completed      (final step after Sepio is verified / not enabled)
 */
class CustomerOnboardingHistorySeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('email', 'admin@admin.com')->first();
        if (!$admin) {
            $this->command->warn('  Skipping CustomerOnboardingHistorySeeder — admin user not found.');
            return;
        }

        $customers = Customer::orderBy('id')->get();
        $count = 0;

        foreach ($customers as $customer) {
            if (CustomerOnboardingHistory::where('customer_id', $customer->id)->exists()) {
                continue;
            }

            $rows = $this->historyRowsFor($customer, $admin);
            foreach ($rows as $i => $row) {
                CustomerOnboardingHistory::insert([
                    'customer_id' => $customer->id,
                    'from_status' => $row['from_status'],
                    'to_status' => $row['to_status'],
                    'actor_type' => $row['actor_type'],
                    'actor_id' => $row['actor_id'],
                    'remarks' => $row['remarks'],
                    'remarks_file_url' => $row['remarks_file_url'] ?? null,
                    'created_at' => $row['created_at'],
                ]);
                $count++;
            }
        }

        $this->command->info("  CustomerOnboardingHistorySeeder: {$count} history rows seeded.");
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function historyRowsFor(Customer $customer, User $admin): array
    {
        $current = $customer->onboarding_status instanceof CustomerOnboardingStatus
            ? $customer->onboarding_status->value
            : $customer->onboarding_status;

        // Synthesize timestamps so the timeline reads top-to-bottom
        $createdAt = $customer->created_at ?? now()->subDays(60);
        $t1 = (clone $createdAt)->addDays(2);   // submitted
        $t2 = (clone $createdAt)->addDays(5);   // il decision
        $t3 = (clone $createdAt)->addDays(8);   // final
        $customerUser = User::where('customer_id', $customer->id)
            ->whereIn('role_id', User::whereIn('name', ['customer_admin'])->pluck('id'))
            ->first();

        $customerActorId = $customerUser?->id ?? $admin->id;
        $ilActorId = $admin->id;

        return match ($current) {

            CustomerOnboardingStatus::Pending->value => [
                // 1. Customer self-registered (implicit `null → pending`)
                [
                    'from_status' => null,
                    'to_status' => CustomerOnboardingStatus::Pending->value,
                    'actor_type' => 'customer',
                    'actor_id' => $customerActorId,
                    'remarks' => 'Customer self-registered. Awaiting profile completion.',
                    'created_at' => $createdAt,
                ],
            ],

            CustomerOnboardingStatus::Submitted->value => [
                [
                    'from_status' => null,
                    'to_status' => CustomerOnboardingStatus::Pending->value,
                    'actor_type' => 'customer',
                    'actor_id' => $customerActorId,
                    'remarks' => 'Customer self-registered.',
                    'created_at' => $createdAt,
                ],
                [
                    'from_status' => CustomerOnboardingStatus::Pending->value,
                    'to_status' => CustomerOnboardingStatus::Submitted->value,
                    'actor_type' => 'customer',
                    'actor_id' => $customerActorId,
                    'remarks' => 'Customer submitted onboarding for review.',
                    'created_at' => $t1,
                ],
            ],

            CustomerOnboardingStatus::IlParked->value => [
                $this->row(null, CustomerOnboardingStatus::Pending->value, 'customer', $customerActorId, 'Customer self-registered.', $createdAt),
                $this->row(CustomerOnboardingStatus::Pending->value, CustomerOnboardingStatus::Submitted->value, 'customer', $customerActorId, 'Customer submitted onboarding for review.', $t1),
                $this->row(CustomerOnboardingStatus::Submitted->value, CustomerOnboardingStatus::IlParked->value, 'platform', $ilActorId, $customer->il_remarks ?: 'Parked pending additional documents.', $t2, $customer->il_remarks_file_url),
            ],

            CustomerOnboardingStatus::IlApproved->value => [
                $this->row(null, CustomerOnboardingStatus::Pending->value, 'customer', $customerActorId, 'Customer self-registered.', $createdAt),
                $this->row(CustomerOnboardingStatus::Pending->value, CustomerOnboardingStatus::Submitted->value, 'customer', $customerActorId, 'Customer submitted onboarding for review.', $t1),
                $this->row(CustomerOnboardingStatus::Submitted->value, CustomerOnboardingStatus::IlApproved->value, 'platform', $ilActorId, $customer->il_remarks ?: 'All documents verified. Approved.', $t2, $customer->il_remarks_file_url),
            ],

            CustomerOnboardingStatus::IlRejected->value => [
                $this->row(null, CustomerOnboardingStatus::Pending->value, 'customer', $customerActorId, 'Customer self-registered.', $createdAt),
                $this->row(CustomerOnboardingStatus::Pending->value, CustomerOnboardingStatus::Submitted->value, 'customer', $customerActorId, 'Customer submitted onboarding for review.', $t1),
                $this->row(CustomerOnboardingStatus::Submitted->value, CustomerOnboardingStatus::IlRejected->value, 'platform', $ilActorId, $customer->il_remarks ?: 'Documentation insufficient.', $t2, $customer->il_remarks_file_url),
            ],

            CustomerOnboardingStatus::Completed->value => [
                $this->row(null, CustomerOnboardingStatus::Pending->value, 'customer', $customerActorId, 'Customer self-registered.', $createdAt),
                $this->row(CustomerOnboardingStatus::Pending->value, CustomerOnboardingStatus::Submitted->value, 'customer', $customerActorId, 'Customer submitted onboarding for review.', $t1),
                $this->row(CustomerOnboardingStatus::Submitted->value, CustomerOnboardingStatus::IlApproved->value, 'platform', $ilActorId, $customer->il_remarks ?: 'All documents verified. Approved.', $t2, $customer->il_remarks_file_url),
                $this->row(CustomerOnboardingStatus::IlApproved->value, CustomerOnboardingStatus::Completed->value, 'platform', $ilActorId, 'Onboarding completed. Wallet, ports, and routes provisioned.', $t3),
            ],

            default => [],
        };
    }

    private function row(
        ?string $from, string $to, string $actorType, int $actorId,
        ?string $remarks, $createdAt, ?string $remarksFileUrl = null
    ): array {
        return [
            'from_status' => $from,
            'to_status' => $to,
            'actor_type' => $actorType,
            'actor_id' => $actorId,
            'remarks' => $remarks,
            'remarks_file_url' => $remarksFileUrl,
            'created_at' => $createdAt,
        ];
    }
}
