<?php

namespace App\Policies;

use App\Models\{SealOrder, User};

class SealOrderPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('seal_order.view');
    }

    public function view(User $user, SealOrder $order): bool
    {
        if (!$user->hasPermission('seal_order.view')) return false;
        if ($user->isPlatformUser()) return true;
        return $order->customer_id === $user->customer_id;
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('seal_order.create');
    }

    public function approve(User $user, SealOrder $order): bool
    {
        return $user->hasPermission('seal_order.approve');
    }

    public function reject(User $user, SealOrder $order): bool
    {
        return $user->hasPermission('seal_order.reject');
    }

    public function park(User $user, SealOrder $order): bool
    {
        return $user->hasPermission('seal_order.park');
    }
}
