<?php

namespace App\Models;

use App\Enums\UserStatus;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasApiTokens;

    protected $fillable = [
        'role_id', 'customer_id', 'name', 'email', 'mobile',
        'password', 'status', 'last_login_at', 'created_by_id',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'status' => UserStatus::class,
        ];
    }

    // ── Relationships ─────────────────────────────────────────────────────────

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    public function isPlatformUser(): bool
    {
        return is_null($this->customer_id);
    }

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function isClientUser(): bool
    {
        return !is_null($this->customer_id);
    }

    public function hasPermission(string $permission): bool
    {
        // Requires role.permissions to be loaded — always eager-load on auth
        return $this->relationLoaded('role') && $this->role->relationLoaded('permissions')
            ? $this->role->permissions->contains('name', $permission)
            : $this->role->permissions()->where('name', $permission)->exists();
    }

    public function isActive(): bool
    {
        return $this->status === UserStatus::Active;
    }

    public function hasRole(string $role): bool
    {
        return $this->relationLoaded('role')
            ? $this->role->name == $role
            : $this->role()->where('name', $role)->exis();
    }

    public function isDriver(): bool
    {
        return $this->hasRole('driver');
    }
}
