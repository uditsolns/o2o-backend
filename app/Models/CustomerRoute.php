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
        'dispatch_location_id', 'delivery_location_id',
        'origin_port_id', 'destination_port_id',
        'notes', 'is_active', 'created_by_id',
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

    public function dispatchLocation(): BelongsTo
    {
        return $this->belongsTo(CustomerLocation::class, 'dispatch_location_id');
    }

    public function deliveryLocation(): BelongsTo
    {
        return $this->belongsTo(CustomerLocation::class, 'delivery_location_id');
    }

    public function originPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'origin_port_id');
    }

    public function destinationPort(): BelongsTo
    {
        return $this->belongsTo(Port::class, 'destination_port_id');
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
