<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('user.view');
    }

    public function view(User $user, User $target): bool
    {
        if (!$user->hasPermission('user.view')) return false;
        if ($user->isPlatformUser()) return $target->isPlatformUser();
        return $target->customer_id === $user->customer_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('user.create');
    }

    public function update(User $user, User $target): bool
    {
        if (!$user->hasPermission('user.update')) return false;
        if ($user->isPlatformUser()) return $target->isPlatformUser();
        return $target->customer_id === $user->customer_id;
    }

    public function delete(User $user, User $target): bool
    {
        if (!$user->hasPermission('user.delete')) return false;
        // Cannot delete yourself
        if ($user->id === $target->id) return false;
        if ($user->isPlatformUser()) return $target->isPlatformUser();
        return $target->customer_id === $user->customer_id;
    }

    public function toggleActive(User $user, User $target): bool
    {
        return $this->update($user, $target);
    }
}
