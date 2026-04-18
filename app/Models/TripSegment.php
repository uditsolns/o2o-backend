<?php

namespace App\Models;

use App\Enums\TripSegmentTrackingSource;
use App\Enums\TripSegmentTransportMode;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripSegment extends Model
{
    protected $fillable = [
        'trip_id', 'customer_id', 'sequence',
        'source_name', 'destination_name',
        'transport_mode', 'tracking_source', 'notes',
    ];

    protected $casts = [
        'transport_mode' => TripSegmentTransportMode::class,
        'tracking_source' => TripSegmentTrackingSource::class,
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
