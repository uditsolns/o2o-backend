<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLocation extends Model
{
    protected $fillable = [
        'customer_id', 'name', 'gst_number', 'address', 'landmark',
        'city', 'state', 'pincode', 'country', 'contact_person', 'contact_number',
        'lat', 'lng', 'sepio_billing_address_id', 'sepio_shipping_address_id', 'is_active', 'created_by_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }

    /**
     * Whether this location has been fully synced to Sepio
     * (both billing and shipping address IDs present).
     */
    public function isSepioSynced(): bool
    {
        return !empty($this->sepio_billing_address_id) && !empty($this->sepio_shipping_address_id);
    }
}
