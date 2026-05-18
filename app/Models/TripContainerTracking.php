<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripContainerTracking extends Model
{
    protected $table = 'trip_container_tracking';

    protected $fillable = [
        'trip_id', 'customer_id', 'container_number', 'carrier_scac', 'carrier_name',
        'container_iso_code', 'container_type_name', 'container_size',
        'mt_tracking_request_id', 'mt_shipment_id', 'mt_vessel_ship_id',
        'tracking_status', 'failed_reason',
        'transportation_status', 'arrival_delay_days', 'initial_carrier_eta',
        'has_rollover',
        'pol_name', 'pol_unlocode', 'pol_lat', 'pol_lng', 'pol_country',
        'pol_etd', 'pol_vessel_name', 'pol_vessel_imo', 'pol_voyage_number',
        'pod_name', 'pod_unlocode', 'pod_lat', 'pod_lng', 'pod_country',
        'pod_arrival_status', 'pod_actual_arrival',
        'current_vessel_name', 'current_vessel_imo', 'current_vessel_mmsi',
        'current_vessel_lat', 'current_vessel_lng', 'current_vessel_speed',
        'current_vessel_heading', 'current_vessel_geo_area', 'current_vessel_position_at',
        'current_vessel_destination', 'current_vessel_current_port', 'current_vessel_ais_eta',
        'last_synced_at', 'last_vessel_position_at',
        'raw_shipment_snapshot', 'eta_history', 'rollover_history', 'transshipment_ports',
    ];

    protected $casts = [
        'has_rollover' => 'boolean',
        'raw_shipment_snapshot' => 'array',
        'eta_history' => 'array',
        'rollover_history' => 'array',
        'transshipment_ports' => 'array',
        'container_size' => 'array',
        'initial_carrier_eta' => 'datetime',
        'pol_etd' => 'datetime',
        'pod_actual_arrival' => 'datetime',
        'current_vessel_position_at' => 'datetime',
        'current_vessel_ais_eta' => 'datetime',
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
}
