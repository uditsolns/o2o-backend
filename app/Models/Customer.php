<?php

namespace App\Models;

use App\Enums\CustomerOnboardingStatus;
use Illuminate\Database\Eloquent\Casts\AsEncryptedArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class Customer extends Model
{
    protected $fillable = [
        'first_name', 'last_name', 'company_name', 'email', 'mobile',
        'company_type', 'industry_type', 'onboarding_status', 'sepio_company_id',
        'gst_number', 'pan_number', 'iec_number', 'cin_number', 'tin_number', 'cha_number',
        'is_active', 'billing_address', 'billing_landmark', 'billing_city', 'billing_state',
        'billing_pincode', 'billing_country',
        'primary_contact_name', 'primary_contact_email', 'primary_contact_mobile',
        'alternate_contact_name', 'alternate_contact_phone', 'alternate_contact_email',
        'il_approved_by_id', 'il_approved_at', 'il_remarks', 'created_by_id',
        'sepio_token', 'sepio_token_expires_at', 'sepio_credentials',
    ];

    protected $casts = [
        'onboarding_status' => CustomerOnboardingStatus::class,
        'il_approved_at' => 'datetime',
        'is_active' => 'boolean',
        'sepio_token_expires_at' => 'datetime',
        'sepio_credentials' => AsEncryptedArrayObject::class,
    ];

    protected $hidden = ['sepio_token', 'sepio_credentials'];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'il_approved_by_id');
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function signatories(): HasMany
    {
        return $this->hasMany(AuthorizedSignatory::class);
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CustomerDocument::class);
    }

    public function ports(): HasMany
    {
        return $this->hasMany(CustomerPort::class);
    }

    public function locations(): HasMany
    {
        return $this->hasMany(CustomerLocation::class);
    }

    public function routes(): HasMany
    {
        return $this->hasMany(CustomerRoute::class);
    }

    public function wallet(): HasOne
    {
        return $this->hasOne(CustomerWallet::class);
    }

    public function sealPricingTiers(): HasMany
    {
        return $this->hasMany(SealPricingTier::class, 'customer_id');
    }

    public function sealOrders(): HasMany
    {
        return $this->hasMany(SealOrder::class);
    }

    public function seals(): HasMany
    {
        return $this->hasMany(Seal::class);
    }

    public function trips(): HasMany
    {
        return $this->hasMany(Trip::class);
    }

    public function isOnboarded(): bool
    {
        return $this->onboarding_status === CustomerOnboardingStatus::Completed;
    }
}
