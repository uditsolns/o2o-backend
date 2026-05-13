<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripContainerTracking extends Model
{
    protected $table = 'trip_container_tracking';

    protected $fillable = [
        'trip_id', 'customer_id', 'container_number', 'carrier_scac',
        'mt_tracking_request_id', 'mt_shipment_id', 'tracking_status', 'failed_reason',
        'transportation_status', 'arrival_delay_days', 'initial_carrier_eta',
        'has_rollover', 'pol_name', 'pol_unlocode', 'pod_name', 'pod_unlocode',
        'current_vessel_name', 'current_vessel_imo',
        'current_vessel_lat', 'current_vessel_lng', 'current_vessel_speed',
        'current_vessel_heading', 'current_vessel_geo_area', 'current_vessel_position_at',
        'last_synced_at', 'raw_shipment_snapshot',
        'eta_history',
        'rollover_history',
        'transshipment_ports',
    ];

    protected $casts = [
        'has_rollover' => 'boolean',
        'raw_shipment_snapshot' => 'array',
        'eta_history' => 'array',
        'rollover_history' => 'array',
        'transshipment_ports' => 'array',
        'initial_carrier_eta' => 'datetime',
        'current_vessel_position_at' => 'datetime',
        'last_synced_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function isActive(): bool
    {
        return $this->tracking_status === 'active';
    }
}
