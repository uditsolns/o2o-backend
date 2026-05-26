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
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;

class CustomerService
{
    public function store(array $data, User $createdBy): Customer
    {
        [$customer, $user, $plainPassword] = DB::transaction(function () use ($data, $createdBy) {
            $customer = Customer::create([
                ...$data,
                'sepio_enabled' => (bool)($data['sepio_enabled'] ?? false),
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

            return [$customer, $user, $plainPassword];
        });

        $user->notify(new UserInvited($plainPassword));

        return $customer;
    }

    public function approve(Customer $customer, array $data, User $by): Customer
    {
        $customer->update([
            'onboarding_status' => CustomerOnboardingStatus::IlApproved,
            'il_approved_by_id' => $by->id,
            'il_approved_at' => now(),
            'il_remarks' => $data['remarks'] ?? null,
        ]);

        if ($customer->sepio_enabled) {
            // Sepio path: register company → sync → upload docs → await verification
            $customer->update(['sepio_status' => SepioStatus::Pending]);
            SepioOnboardCustomerJob::dispatch($customer->fresh());
        } else {
            // Non-Sepio path: platform approval is sufficient — go straight to completed
            $customer->update(['onboarding_status' => CustomerOnboardingStatus::Completed]);
        }

        return $customer->fresh();
    }

    // New method: Enable Sepio for an existing customer
    public function enableSepio(Customer $customer): Customer
    {
        abort_if($customer->sepio_enabled, 422, 'Sepio is already enabled for this customer.');

        $this->assertSepioReadiness($customer);

        $customer->update([
            'sepio_enabled' => true,
            'sepio_status' => SepioStatus::Pending,
        ]);

        SepioOnboardCustomerJob::dispatch($customer->fresh());

        return $customer->fresh();
    }

    public function getSepioReadiness(Customer $customer): array
    {
        $uploadedDocTypes = $customer->documents
            ->map(fn($d) => is_string($d->doc_type) ? $d->doc_type : $d->doc_type->value)
            ->all();

        $hasIec = !empty($customer->iec_number);
        $hasPortPort = $customer->ports()->where('port_category', 'port')->exists();
        $hasIcdPort = $customer->ports()->where('port_category', 'icd')->exists();
        $hasIecDoc = in_array('iec_cert', $uploadedDocTypes, true);
        $hasPanDoc = in_array('pan_card', $uploadedDocTypes, true);
        $hasCorDoc = in_array('certificate_of_registration', $uploadedDocTypes, true);
        $hasSelfStuffingDoc = in_array('self_stuffing_cert', $uploadedDocTypes, true);
        $hasActiveBillingLocation = $customer->locations()->where('is_active', true)->exists();

        $checks = [
            'iec_number' => ['met' => $hasIec, 'message' => 'IEC number must be filled in the customer profile.'],
            'port_assigned' => ['met' => $hasPortPort, 'message' => 'At least one Port (port category) must be assigned.'],
            'icd_assigned' => ['met' => $hasIcdPort, 'message' => 'At least one ICD port must be assigned.'],
            'iec_cert' => ['met' => $hasIecDoc, 'message' => 'IEC certificate document must be uploaded.'],
            'pan_card' => ['met' => $hasPanDoc, 'message' => 'PAN card document must be uploaded.'],
            'certificate_of_registration' => ['met' => $hasCorDoc, 'message' => 'Certificate of Registration document must be uploaded.'],
            'self_stuffing_cert' => ['met' => $hasSelfStuffingDoc, 'message' => 'Self Stuffing Certificate document must be uploaded.'],
            'billing_location' => ['met' => $hasActiveBillingLocation, 'message' => 'At least one active billing location must exist.'],
        ];

        $isReady = collect($checks)->every(fn($check) => $check['met']);

        return [
            'is_ready' => $isReady,
            'checks' => $checks,
            'missing' => collect($checks)
                ->filter(fn($check) => !$check['met'])
                ->map(fn($check) => $check['message'])
                ->values()
                ->all(),
        ];
    }

    private function assertSepioReadiness(Customer $customer): void
    {
        $customer->load('ports', 'locations', 'documents');
        $readiness = $this->getSepioReadiness($customer);

        if (!$readiness['is_ready']) {
            abort(response()->json([
                'message' => 'Customer is not ready for Sepio integration.',
                'missing' => $readiness['missing'],
                'checks' => $readiness['checks'],
            ], 422));
        }
    }

    public function update(Customer $customer, array $data): Customer
    {
        $customer->update($data);
        return $customer->fresh();
    }

    public function reject(Customer $customer, array $data, User $by): Customer
    {
        $customer->update([
            'onboarding_status' => CustomerOnboardingStatus::IlRejected,
            'il_approved_by_id' => $by->id,
            'il_approved_at' => now(),
            'il_remarks' => $data['remarks'],
        ]);

        return $customer->fresh();
    }

    public function park(Customer $customer, array $data, User $by): Customer
    {
        $customer->update([
            'onboarding_status' => CustomerOnboardingStatus::IlParked,
            'il_approved_by_id' => $by->id,
            'il_approved_at' => now(),
            'il_remarks' => $data['remarks'] ?? null,
        ]);

        return $customer->fresh();
    }

    public function toggleActive(Customer $customer): Customer
    {
        $isBeingDeactivated = $customer->is_active;

        $customer->update(['is_active' => !$customer->is_active]);

        // Immediately invalidate all sessions for this customer's users
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
