<?php

namespace App\Policies;

use App\Models\CustomerDocument;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class CustomerDocumentPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {

    }

    public function view(User $user, CustomerDocument $customerDocument): bool
    {
    }

    public function create(User $user): bool
    {
    }

    public function update(User $user, CustomerDocument $customerDocument): bool
    {
    }

    public function delete(User $user, CustomerDocument $customerDocument): bool
    {
    }

    public function restore(User $user, CustomerDocument $customerDocument): bool
    {
    }

    public function forceDelete(User $user, CustomerDocument $customerDocument): bool
    {
    }
}
