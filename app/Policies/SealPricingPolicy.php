<?php

namespace App\Policies;

use App\Models\{SealPricingTier, User};

class SealPricingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('pricing.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('pricing.manage');
    }

    public function update(User $user, SealPricingTier $tier): bool
    {
        return $user->hasPermission('pricing.manage');
    }

    public function delete(User $user, SealPricingTier $tier): bool
    {
        return $user->hasPermission('pricing.manage');
    }
}
