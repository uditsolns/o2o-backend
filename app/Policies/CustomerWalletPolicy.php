<?php

namespace App\Policies;

use App\Models\{CustomerWallet, User};

class CustomerWalletPolicy
{
    public function view(User $user, CustomerWallet $wallet): bool
    {
        if (!$user->hasPermission('wallet.view')) return false;
        if ($user->isPlatformUser()) return true;
        return $wallet->customer_id === $user->customer_id;
    }

    public function manage(User $user): bool
    {
        return $user->hasPermission('wallet.manage');
    }
}
