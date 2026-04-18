<?php

namespace App\Models;

use App\Enums\TripTransportationMode;
use App\Enums\TripType;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerRoute extends Model
{
    protected $fillable = [
        'customer_id', 'name', 'trip_type', 'transport_mode',
        // Dispatch snapshot
        'dispatch_location_name', 'dispatch_address', 'dispatch_city', 'dispatch_state',
        'dispatch_pincode', 'dispatch_country', 'dispatch_lat', 'dispatch_lng',
        // Delivery snapshot
        'delivery_location_name', 'delivery_address', 'delivery_city', 'delivery_state',
        'delivery_pincode', 'delivery_country', 'delivery_lat', 'delivery_lng',
        // Port snapshots
        'origin_port_name', 'origin_port_code', 'origin_port_category',
        'destination_port_name', 'destination_port_code', 'destination_port_category',
        'is_active', 'created_by_id',
    ];

    protected $casts = [
        'trip_type' => TripType::class,
        'transport_mode' => TripTransportationMode::class,
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
}
