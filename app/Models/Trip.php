<?php

namespace App\Models;

use App\Enums\{TripStatus, TripType, TripTransportationMode};
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany, HasOne};

class Trip extends Model
{
    protected $fillable = [
        'customer_id', 'created_by_id', 'driver_user_id', 'seal_id', 'uses_sepio_seal', 'trip_ref',
        'status', 'trip_type', 'transport_mode', 'risk_score',
        'driver_name', 'driver_license', 'driver_aadhaar', 'driver_phone',
        'is_driver_license_verified', 'is_driver_aadhaar_verified',
        'driver_license_verification_payload', 'driver_aadhaar_verification_payload',
        'vehicle_number', 'vehicle_type', 'is_rc_verified', 'rc_verification_payload',
        'is_verification_done', 'tracking_token', 'last_fastag_synced_at',
        'last_known_lat', 'last_known_lng', 'last_known_source', 'last_tracked_at',
        'container_number', 'container_type',
        'cargo_type', 'cargo_description', 'hs_code',
        'gross_weight', 'net_weight', 'weight_unit',
        'quantity', 'quantity_unit', 'declared_cargo_value',
        'invoice_number', 'invoice_date', 'eway_bill_number', 'eway_bill_validity_date',
        'shipping_bill_no', 'shipping_bill_date',
        'dispatch_location_name', 'dispatch_address', 'dispatch_city', 'dispatch_state',
        'dispatch_pincode', 'dispatch_country', 'dispatch_contact_person',
        'dispatch_contact_number', 'dispatch_contact_email', 'dispatch_lat', 'dispatch_lng',
        'delivery_location_name', 'delivery_address', 'delivery_city', 'delivery_state',
        'delivery_pincode', 'delivery_country', 'delivery_contact_person',
        'delivery_contact_number', 'delivery_contact_email', 'delivery_lat', 'delivery_lng',
        'origin_port_name', 'origin_port_code', 'origin_port_category',
        'destination_port_name', 'destination_port_code', 'destination_port_category',
        'vessel_name', 'vessel_imo_number', 'voyage_number', 'bill_of_lading', 'eta', 'etd',
        'last_vessel_tracked_at', 'dispatch_date', 'trip_start_time', 'expected_delivery_date',
        'actual_delivery_date', 'trip_end_time',
        'epod_status', 'epod_confirmed_at', 'epod_confirmed_by_id', 'epod_confirmation_notes',
        'carrier_scac', 'mt_vessel_ship_id', 'customs_hold', 'last_vessel_position_at',
    ];

    protected $casts = [
        'status' => TripStatus::class,
        'trip_type' => TripType::class,
        'transport_mode' => TripTransportationMode::class,
        'eta' => 'datetime',
        'etd' => 'datetime',
        'trip_start_time' => 'datetime',
        'trip_end_time' => 'datetime',
        'epod_confirmed_at' => 'datetime',
        'dispatch_date' => 'date',
        'invoice_date' => 'date',
        'eway_bill_validity_date' => 'date',
        'expected_delivery_date' => 'date',
        'actual_delivery_date' => 'date',
        'is_driver_license_verified' => 'boolean',
        'is_driver_aadhaar_verified' => 'boolean',
        'driver_license_verification_payload' => 'array',
        'driver_aadhaar_verification_payload' => 'array',
        'is_rc_verified' => 'boolean',
        'rc_verification_payload' => 'array',
        'is_verification_done' => 'boolean',
        'last_fastag_synced_at' => 'datetime',
        'last_tracked_at' => 'datetime',
        'last_vessel_tracked_at' => 'datetime',
        'last_vessel_position_at' => 'datetime',
        'customs_hold' => 'boolean',
        'uses_sepio_seal' => 'boolean',
        'shipping_bill_date' => 'date',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());

        static::creating(function (Trip $trip) {
            if (empty($trip->trip_ref)) {
                $last = static::withoutGlobalScopes()->lockForUpdate()->latest('id')->value('trip_ref');
                $next = $last ? (int)substr($last, 2) + 1 : 1;
                $trip->trip_ref = 'TR' . str_pad($next, 7, '0', STR_PAD_LEFT);
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function driverUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'driver_user_id');
    }

    public function seal(): BelongsTo
    {
        return $this->belongsTo(Seal::class, 'seal_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TripDocument::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TripEvent::class)->orderBy('created_at');
    }

    public function segments(): HasMany
    {
        return $this->hasMany(TripSegment::class)->orderBy('sequence');
    }

    public function trackingPoints(): HasMany
    {
        return $this->hasMany(TripTrackingPoint::class)->orderBy('recorded_at');
    }

    public function containerTracking(): HasOne
    {
        return $this->hasOne(TripContainerTracking::class);
    }

    public function shipmentMilestones(): HasMany
    {
        return $this->hasMany(TripShipmentMilestone::class)->orderBy('sequence_order');
    }

    public function isLocked(): bool
    {
        return $this->status === TripStatus::Completed;
    }

    public function requiresVehicleTracking(): bool
    {
        return in_array($this->transport_mode, [TripTransportationMode::Road, TripTransportationMode::Multimodal], true)
            && in_array($this->status, [TripStatus::InTransit, TripStatus::AtPort], true);
    }

    public function requiresSeaTracking(): bool
    {
        return in_array($this->transport_mode, [TripTransportationMode::Sea, TripTransportationMode::Multimodal], true)
            && !in_array($this->status, [TripStatus::Draft, TripStatus::Completed], true);
    }
}
