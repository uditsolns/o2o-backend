<?php

namespace App\Services;

use App\Enums\CustomerOnboardingStatus;
use App\Enums\SepioStatus;
use App\Enums\UserStatus;
use App\Jobs\SepioOnboardCustomerJob;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvited;
use App\Services\Concerns\ChecksSepioReadiness;
use App\Services\Concerns\RecordsHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class CustomerService
{
    use RecordsHistory;
    use ChecksSepioReadiness;

    public function store(array $data, User $createdBy): Customer
    {
        [$customer, $user, $plainPassword] = DB::transaction(function () use ($data, $createdBy) {
            $customer = Customer::create([
                ...$data,
                'sepio_enabled' => (bool)($data['sepio_enabled'] ?? false),
                // sepio_status stays null until onboarding is approved
                'created_by_id' => $createdBy->id,
            ]);

            $role = Role::where('name', 'customer_admin')->firstOrFail();
            $plainPassword = Str::password(12);

            $user = User::create([
                'role_id' => $role->id,
                'customer_id' => $customer->id,
                'name' => trim($customer->first_name . ' ' . $customer->last_name),
                'email' => $customer->email,
                'password' => bcrypt($plainPassword),
                'status' => UserStatus::Invited,
                'created_by_id' => $createdBy->id,
            ]);

            $this->recordOnboardingHistory(
                $customer->id,
                null,
                CustomerOnboardingStatus::Pending->value,
                $createdBy->id,
                'Customer account created.'
            );

            return [$customer, $user, $plainPassword];
        });

        $user->notify(new UserInvited($plainPassword));

        return $customer;
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        return $customer->fresh();
    }

    public function approve(Customer $customer, array $data, User $by): Customer
    {
        // Guard against double-approval — only customers actively awaiting
        // a decision should be approvable. Submitted or parked are the two
        // states a customer can be in before the IL verdict.
        abort_unless(
            in_array($customer->onboarding_status, [
                CustomerOnboardingStatus::Submitted,
                CustomerOnboardingStatus::IlParked,
            ], true),
            422,
            'Customer is not awaiting IL approval — only submitted or parked customers can be approved.'
        );

        $fromStatus = $customer->onboarding_status->value;

        $customer->update([
            'onboarding_status' => CustomerOnboardingStatus::IlApproved,
            'il_approved_by_id' => $by->id,
            'il_approved_at' => now(),
            'il_remarks' => $data['remarks'] ?? null,
        ]);

        $this->recordOnboardingHistory(
            $customer->id,
            $fromStatus,
            CustomerOnboardingStatus::IlApproved->value,
            $by->id,
            $data['remarks'] ?? null,
        );

        if ($customer->sepio_enabled) {
            // Run the same readiness checks as enable-sepio.
            // assertReadyToSubmit() should have already gated submission,
            // but this is the safety net at approval time.
            $customer->load('ports', 'locations', 'documents');
            $readiness = $this->buildSepioReadiness($customer);

            if (!$readiness['is_ready']) {
                // Roll back the approval — cannot proceed to Sepio with incomplete setup
                $customer->update([
                    'onboarding_status' => CustomerOnboardingStatus::Submitted,
                    'il_approved_by_id' => null,
                    'il_approved_at' => null,
                ]);

                abort(response()->json([
                    'message' => 'Cannot approve: customer is not ready for Sepio integration.',
                    'missing' => $readiness['missing'],
                    'checks' => $readiness['checks'],
                ], 422));
            }

            $customer->update(['sepio_status' => SepioStatus::Pending]);

            $this->recordSepioHistory(
                $customer->id,
                null,
                SepioStatus::Pending->value,
                $by->id,
                'Sepio onboarding initiated on IL approval.'
            );

            SepioOnboardCustomerJob::dispatch($customer->fresh());
        } else {
            // Non-Sepio path: IL approval is sufficient — complete immediately
            $customer->update(['onboarding_status' => CustomerOnboardingStatus::Completed]);

            $this->recordOnboardingHistory(
                $customer->id,
                CustomerOnboardingStatus::IlApproved->value,
                CustomerOnboardingStatus::Completed->value,
                null,
                'Onboarding completed — Sepio integration not enabled for this customer.'
            );
        }

        return $customer->fresh();
    }

    public function reject(Customer $customer, array $data, User $by): Customer
    {
        abort_unless(
            in_array($customer->onboarding_status, [
                CustomerOnboardingStatus::Submitted,
                CustomerOnboardingStatus::IlParked,
            ], true),
            422,
            'Only submitted or parked customers can be rejected.'
        );

        $fromStatus = $customer->onboarding_status->value;
        $remarksFilePath = null;

        if (isset($data['remarks_file'])) {
            $remarksFilePath = $data['remarks_file']->store("customers/{$customer->id}/remarks");
        }

        $customer->update([
            'onboarding_status' => CustomerOnboardingStatus::IlRejected,
            'il_approved_by_id' => $by->id,
            'il_approved_at' => now(),
            'il_remarks' => $data['remarks'],
        ]);

        $this->recordOnboardingHistory(
            $customer->id,
            $fromStatus,
            CustomerOnboardingStatus::IlRejected->value,
            $by->id,
            $data['remarks'],
            $remarksFilePath,
        );

        return $customer->fresh();
    }

    public function park(Customer $customer, array $data, User $by): Customer
    {
        abort_unless(
            $customer->onboarding_status === CustomerOnboardingStatus::Submitted,
            422,
            'Only submitted customers can be parked.'
        );

        $fromStatus = $customer->onboarding_status->value;
        $remarksFilePath = null;

        if (isset($data['remarks_file'])) {
            $remarksFilePath = $data['remarks_file']->store("customers/{$customer->id}/remarks");
        }

        $customer->update([
            'onboarding_status' => CustomerOnboardingStatus::IlParked,
            'il_approved_by_id' => $by->id,
            'il_approved_at' => now(),
            'il_remarks' => $data['remarks'] ?? null,
        ]);

        $this->recordOnboardingHistory(
            $customer->id,
            $fromStatus,
            CustomerOnboardingStatus::IlParked->value,
            $by->id,
            $data['remarks'] ?? null,
            $remarksFilePath,
        );

        return $customer->fresh();
    }

    /**
     * Customer (or platform on behalf) acknowledges rejection.
     * il_rejected → pending — reopens editing.
     */
    public function acknowledgeRejection(Customer $customer, User $by): Customer
    {
        abort_if(
            $customer->onboarding_status !== CustomerOnboardingStatus::IlRejected,
            422,
            'Only rejected onboarding can be acknowledged.'
        );

        $customer->update([
            'onboarding_status' => CustomerOnboardingStatus::Pending,
            'il_remarks' => null,
        ]);

        $this->recordOnboardingHistory(
            $customer->id,
            CustomerOnboardingStatus::IlRejected->value,
            CustomerOnboardingStatus::Pending->value,
            $by->id,
            'Rejection acknowledged. Onboarding reopened for editing.'
        );

        return $customer->fresh();
    }

    /**
     * Platform enables Sepio on an already-completed non-Sepio customer.
     */
    public function enableSepio(Customer $customer, User $by): Customer
    {
        abort_if($customer->sepio_enabled, 422, 'Sepio is already enabled for this customer.');
        abort_if(
            $customer->onboarding_status !== CustomerOnboardingStatus::Completed,
            422,
            'Customer must have completed onboarding before enabling Sepio.'
        );

        $customer->load('ports', 'locations', 'documents');
        $readiness = $this->buildSepioReadiness($customer);

        if (!$readiness['is_ready']) {
            abort(response()->json([
                'message' => 'Customer is not ready for Sepio integration.',
                'missing' => $readiness['missing'],
                'checks' => $readiness['checks'],
            ], 422));
        }

        $customer->update([
            'sepio_enabled' => true,
            'sepio_status' => SepioStatus::Pending,
        ]);

        $this->recordSepioHistory(
            $customer->id,
            null,
            SepioStatus::Pending->value,
            $by->id,
            'Sepio integration enabled by platform user.'
        );

        SepioOnboardCustomerJob::dispatch($customer->fresh());

        return $customer->fresh();
    }

    /**
     * Platform retries Sepio registration after rejection.
     *
     * Resumes from the earliest unfinished step:
     *  - never registered → start from Pending so the job re-attempts registration
     *  - registered, docs rejected → resume at DocsUploaded so the job re-uploads
     *    only the documents and re-waits for verification (no duplicate
     *    pending/registered history)
     */
    public function retrySepioRegistration(Customer $customer, User $by): Customer
    {
        abort_if(!$customer->sepio_enabled, 422, 'Sepio is not enabled for this customer.');
        abort_if(
            $customer->sepio_status !== SepioStatus::Rejected,
            422,
            'Only Sepio-rejected customers can be retried.'
        );

        // Clear document-level rejection reasons so fresh uploads are re-evaluated
        $customer->documents()
            ->whereNotNull('sepio_rejection_reason')
            ->update(['sepio_rejection_reason' => null]);

        $fromStatus = $customer->sepio_status->value;

        // Choose the resume status:
        //   - no sepio_company_id → registration itself failed, restart at Pending
        //   - already registered → jump to Registered so the job picks up from
        //     document upload without rewriting the "pending → registered" event
        $resumeStatus = $customer->sepio_company_id
            ? SepioStatus::Registered
            : SepioStatus::Pending;

        $customer->update(['sepio_status' => $resumeStatus]);

        $this->recordSepioHistory(
            $customer->id,
            $fromStatus,
            $resumeStatus->value,
            $by->id,
            'Sepio registration retry initiated by platform.'
        );

        SepioOnboardCustomerJob::dispatch($customer->fresh());

        return $customer->fresh();
    }

    /**
     * Public readiness check — used by the sepio-readiness endpoint.
     * Delegates to the shared trait.
     */
    public function getSepioReadiness(Customer $customer): array
    {
        $customer->loadMissing('ports', 'locations', 'documents');
        return $this->buildSepioReadiness($customer);
    }

    public function toggleActive(Customer $customer): Customer
    {
        $isBeingDeactivated = $customer->is_active;
        $customer->update(['is_active' => !$customer->is_active]);

        if ($isBeingDeactivated) {
            PersonalAccessToken::whereHasMorph(
                'tokenable',
                [User::class],
                fn($q) => $q->where('customer_id', $customer->id)
            )->delete();
        }

        return $customer->fresh();
    }
}
