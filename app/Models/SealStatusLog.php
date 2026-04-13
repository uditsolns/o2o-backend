<?php

namespace App\Models;

use App\Enums\SepioSealStatus;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SealStatusLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'customer_id', 'seal_id', 'trip_id', 'status', 'checked_at',
        'scan_location', 'scanned_lat', 'scanned_lng', 'scanned_by', 'raw_response',
    ];

    protected $casts = [
        'status' => SepioSealStatus::class,
        'raw_response' => 'array',
        'checked_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function seal(): BelongsTo
    {
        return $this->belongsTo(Seal::class);
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }
}
