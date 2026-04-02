<?php

namespace App\Models;

use App\Enums\SealStatus;
use App\Enums\SepioSealStatus;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Seal extends Model
{
    protected $fillable = [
        'customer_id', 'seal_order_id', 'trip_id',
        'seal_number', 'status', 'sepio_status', 'last_scan_at', 'delivered_at',
    ];

    protected $casts = [
        'status' => SealStatus::class,
        'sepio_status' => SepioSealStatus::class,
        'last_scan_at' => 'datetime',
        'delivered_at' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SealOrder::class, 'seal_order_id');
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(SealStatusLog::class);
    }

    public function isAvailable(): bool
    {
        return $this->status === SealStatus::InInventory;
    }
}
