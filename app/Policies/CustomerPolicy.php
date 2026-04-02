<?php

namespace App\Policies;

use App\Models\{Customer, User};

class CustomerPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('customer.view');
    }

    public function view(User $user, Customer $customer): bool
    {
        if (!$user->hasPermission('customer.view')) return false;
        // Client users can only see their own company
        if ($user->isClientUser()) return $user->customer_id === $customer->id;
        return true;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('customer.create');
    }

    public function update(User $user, Customer $customer): bool
    {
        if (!$user->hasPermission('customer.update')) return false;
        if ($user->isClientUser()) return $user->customer_id === $customer->id;
        return true;
    }

    public function approve(User $user, Customer $customer): bool
    {
        return $user->hasPermission('customer.approve');
    }

    public function reject(User $user, Customer $customer): bool
    {
        return $user->hasPermission('customer.reject');
    }

    public function park(User $user, Customer $customer): bool
    {
        return $user->hasPermission('customer.park');
    }
}
