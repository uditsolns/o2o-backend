<?php

namespace App\Models;

use App\Enums\LocationType;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerLocation extends Model
{
    protected $fillable = [
        'customer_id', 'location_type', 'name', 'gst_number', 'address', 'landmark',
        'city', 'state', 'pincode', 'country', 'contact_person', 'contact_number',
        'lat', 'lng', 'sepio_address_id', 'is_active', 'created_by_id',
    ];

    protected $casts = [
        'location_type' => LocationType::class,
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

    public function scopeBilling($q)
    {
        return $q->whereIn('location_type', ['billing', 'both']);
    }

    public function scopeShipping($q)
    {
        return $q->whereIn('location_type', ['shipping', 'both']);
    }
}
