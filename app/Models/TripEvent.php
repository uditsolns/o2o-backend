<?php

namespace App\Models;

use App\Enums\TripStatus;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'customer_id', 'trip_id', 'event_type',
        'previous_status', 'new_status', 'event_data',
        'actor_type', 'actor_id',
    ];

    protected $casts = [
        'previous_status' => TripStatus::class,
        'new_status' => TripStatus::class,
        'event_data' => 'array',
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
