<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripContainerTracking extends Model
{
    protected $table = 'trip_container_tracking';

    protected $fillable = [
        'trip_id',
        'customer_id',
        'container_number',
        'carrier_scac',
        'mt_tracking_request_id',
        'mt_shipment_id',
        'mt_vessel_ship_id',
        'tracking_status',
        'failed_reason',
        'transportation_status',
        'is_routing_inconclusive',
        'transportation_status_updated_at',
        'current_vessel_imo',
        'last_synced_at',
        'last_vessel_position_at',
        // JSON snapshot columns
        'carrier',
        'container_specs',
        'pol',
        'pod',
        'current_vessel',
        'insights',
        'eta_history',
        'rollover_history',
        'transshipment_ports',
        'pol_change_history',
        'pod_change_history',
        'raw_shipment_snapshot',
    ];

    protected $casts = [
        // JSON columns
        'carrier' => 'array',
        'container_specs' => 'array',
        'pol' => 'array',
        'pod' => 'array',
        'current_vessel' => 'array',
        'insights' => 'array',
        'eta_history' => 'array',
        'rollover_history' => 'array',
        'transshipment_ports' => 'array',
        'pol_change_history' => 'array',
        'pod_change_history' => 'array',
        'raw_shipment_snapshot' => 'array',
        // Operational
        'is_routing_inconclusive' => 'boolean',
        'transportation_status_updated_at' => 'datetime',
        'last_synced_at' => 'datetime',
        'last_vessel_position_at' => 'datetime',
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

    public function getInsightValue(string $key): mixed
    {
        return data_get($this->insights, $key);
    }

    public function getPolValue(string $key): mixed
    {
        return data_get($this->pol, $key);
    }

    public function getPodValue(string $key): mixed
    {
        return data_get($this->pod, $key);
    }

    public function getCurrentVesselValue(string $key): mixed
    {
        return data_get($this->current_vessel, $key);
    }
}
