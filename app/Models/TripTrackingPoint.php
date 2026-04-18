<?php

namespace App\Models;

use App\Enums\TripSegmentTrackingSource;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripTrackingPoint extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'trip_id', 'customer_id', 'source',
        'lat', 'lng', 'speed', 'heading', 'accuracy',
        'location_name', 'external_id', 'recorded_at', 'raw_payload',
    ];

    protected $casts = [
        'source' => TripSegmentTrackingSource::class,
        'raw_payload' => 'array',
        'recorded_at' => 'datetime',
        'created_at' => 'datetime',
        'lat' => 'float',
        'lng' => 'float',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
