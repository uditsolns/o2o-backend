<?php

namespace App\Models;

use App\Enums\TripTransportationMode;
use App\Enums\TripType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripPricingRule extends Model
{
    protected $fillable = [
        'customer_id', 'trip_type', 'transport_mode',
        'price_per_trip', 'is_active', 'created_by_id',
    ];

    protected $casts = [
        'trip_type' => TripType::class,
        'transport_mode' => TripTransportationMode::class,
        'is_active' => 'boolean',
        'price_per_trip' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
