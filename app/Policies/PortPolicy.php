<?php

namespace App\Policies;

use App\Models\{Port, User};

class PortPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission('port.view');
    }

    public function view(User $user, Port $port): bool
    {
        return $user->hasPermission('port.view');
    }

    public function create(User $user): bool
    {
        return $user->hasPermission('port.manage');
    }

    public function update(User $user, Port $port): bool
    {
        return $user->hasPermission('port.manage');
    }
}
