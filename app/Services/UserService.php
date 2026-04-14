<?php

namespace App\Services;

use App\Enums\UserStatus;
use App\Models\Role;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

class UserService
{
    public function paginate(User $auth, array $filters = []): LengthAwarePaginator
    {
        return User::with('role')
            ->when(
                $auth->isClientUser(),
                fn($q) => $q->where('customer_id', $auth->customer_id),
                fn($q) => $q->when(
                    isset($filters['customer_id']),
                    fn($q) => $q->where('customer_id', $filters['customer_id']),
                    fn($q) => $q->whereNull('customer_id') // default: platform users only
                )
            )
            ->when(isset($filters['status']), fn($q) => $q->where('status', $filters['status']))
            ->latest()
            ->paginate(20);
    }

    /**
     * Create a user, auto-assign role by context, send invitation.
     */
    public function store(array $data, User $createdBy): User
    {
        $user = User::create([
            'role_id' => $data['role_id'],
            'customer_id' => $data['customer_id'] ?? $user->customer_id ?? null,
            'name' => $data['name'],
            'email' => $data['email'],
            'mobile' => $data['mobile'] ?? null,
            'password' => $data['password'],
            'status' => UserStatus::Active,
            'created_by_id' => $createdBy->id,
        ]);

        return $user->load('role.permissions');
    }

    public function update(User $target, array $data): User
    {
        $target->update($data);
        return $target->fresh(['role.permissions']);
    }

    public function toggleActive(User $target): User
    {
        $newStatus = $target->status === UserStatus::Active
            ? UserStatus::Inactive
            : UserStatus::Active;

        $target->update(['status' => $newStatus]);

        return $target->fresh();
    }

    public function delete(User $target): void
    {
        // Revoke all tokens before deleting
        $target->tokens()->delete();
        $target->delete();
    }
}
