<?php

namespace App\Policies;

use App\Models\{Seal, User};

class SealPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('seal.view');
    }

    public function view(User $user, Seal $seal): bool
    {
        if (!$user->hasPermission('seal.view')) return false;
        if ($user->isPlatformUser()) return true;
        return $seal->customer_id === $user->customer_id;
    }

    public function assign(User $user, Seal $seal): bool
    {
        if (!$user->hasPermission('seal.assign')) return false;
        if ($user->isPlatformUser()) return true;
        return $seal->customer_id === $user->customer_id;
    }
}
