<?php

namespace App\Policies;

use App\Models\{CustomerLocation, User};

class LocationPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('location.view');
    }

    public function view(User $user, CustomerLocation $location): bool
    {
        if (!$user->hasPermission('location.view')) return false;
        if ($user->isPlatformUser()) return true;
        return $location->customer_id === $user->customer_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('location.create');
    }

    public function update(User $user, CustomerLocation $location): bool
    {
        if (!$user->hasPermission('location.update')) return false;
        if ($user->isPlatformUser()) return true;
        return $location->customer_id === $user->customer_id;
    }

    public function delete(User $user, CustomerLocation $location): bool
    {
        if (!$user->hasPermission('location.delete')) return false;
        if ($user->isPlatformUser()) return true;
        return $location->customer_id === $user->customer_id;
    }
}
