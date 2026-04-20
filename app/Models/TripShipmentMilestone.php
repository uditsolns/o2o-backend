<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripShipmentMilestone extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'trip_id', 'customer_id', 'mt_event_id', 'event_type', 'event_classifier',
        'location_name', 'location_unlocode', 'location_country',
        'location_lat', 'location_lng', 'terminal_name',
        'vessel_name', 'vessel_imo', 'voyage_number',
        'location_type', 'sequence_order', 'occurred_at',
    ];

    protected $casts = [
        'occurred_at' => 'datetime',
        'created_at' => 'datetime',
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
