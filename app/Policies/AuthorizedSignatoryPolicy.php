<?php

namespace App\Policies;

use App\Models\AuthorizedSignatory;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class AuthorizedSignatoryPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {

    }

    public function view(User $user, AuthorizedSignatory $authorizedSignatory): bool
    {
    }

    public function create(User $user): bool
    {
    }

    public function update(User $user, AuthorizedSignatory $authorizedSignatory): bool
    {
    }

    public function delete(User $user, AuthorizedSignatory $authorizedSignatory): bool
    {
    }

    public function restore(User $user, AuthorizedSignatory $authorizedSignatory): bool
    {
    }

    public function forceDelete(User $user, AuthorizedSignatory $authorizedSignatory): bool
    {
    }
}
