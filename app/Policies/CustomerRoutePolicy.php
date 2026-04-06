<?php

namespace App\Policies;

use App\Models\{CustomerRoute, User};

class CustomerRoutePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('route.view');
    }

    public function view(User $user, CustomerRoute $route): bool
    {
        if (!$user->hasPermission('route.view')) return false;
        if ($user->isPlatformUser()) return true;
        return $route->customer_id === $user->customer_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('route.create');
    }

    public function update(User $user, CustomerRoute $route): bool
    {
        if (!$user->hasPermission('route.update')) return false;
        if ($user->isPlatformUser()) return true;
        return $route->customer_id === $user->customer_id;
    }

    public function delete(User $user, CustomerRoute $route): bool
    {
        if (!$user->hasPermission('route.delete')) return false;
        if ($user->isPlatformUser()) return true;
        return $route->customer_id === $user->customer_id;
    }
}
