<?php

namespace App\Services;

use App\Enums\CustomerOnboardingStatus;
use App\Enums\UserStatus;
use App\Jobs\SepioOnboardCustomerJob;
use App\Models\Customer;
use App\Models\Role;
use App\Models\User;
use App\Notifications\UserInvited;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class CustomerService
{
    /**
     * Create customer + initial customer_admin user + send invitation.
     */
    public function store(array $data, User $createdBy): Customer
    {
        [$customer, $user, $plainPassword] = DB::transaction(function () use ($data, $createdBy) {
            $customer = Customer::create([...$data, 'created_by_id' => $createdBy->id]);

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

        // Outside transaction — mail failure won't roll back DB
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
        $customer->update([
            'onboarding_status' => CustomerOnboardingStatus::IlApproved,
            'il_approved_by_id' => $by->id,
            'il_approved_at' => now(),
            'il_remarks' => $data['remarks'] ?? null,
        ]);

        // Single job handles: register → login → sync locations → upload docs
        SepioOnboardCustomerJob::dispatch($customer->fresh());

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
        $customer->update(['is_active' => !$customer->is_active]);
        return $customer->fresh();
    }
}
