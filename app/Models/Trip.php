<?php

namespace App\Models;

use App\Enums\{TripStatus, TripType, TripTransportationMode};
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class Trip extends Model
{
    protected $fillable = [
        'customer_id', 'created_by_id', 'seal_id', 'route_id', 'trip_ref',
        'status', 'trip_type', 'transport_mode', 'risk_score',
        'driver_name', 'driver_license', 'driver_aadhaar', 'driver_phone', 'is_driver_license_verified',
        'is_driver_aadhaar_verified', 'driver_license_verification_payload', 'driver_aadhaar_verification_payload',
        'vehicle_number', 'vehicle_type', 'transporter_name', 'transporter_id', 'is_rc_verified',
        'rc_verification_payload', 'is_verification_done',
        'container_number', 'container_type', 'seal_issue_date',
        'cargo_type', 'cargo_description', 'hs_code',
        'gross_weight', 'net_weight', 'weight_unit',
        'quantity', 'quantity_unit', 'declared_cargo_value',
        'invoice_number', 'invoice_date', 'eway_bill_number', 'eway_bill_validity_date',
        'dispatch_location_name', 'dispatch_address', 'dispatch_city', 'dispatch_state',
        'dispatch_pincode', 'dispatch_country', 'dispatch_contact_person', 'dispatch_contact_number',
        'dispatch_lat', 'dispatch_lng',
        'delivery_location_name', 'delivery_address', 'delivery_city', 'delivery_state',
        'delivery_pincode', 'delivery_country', 'delivery_contact_person', 'delivery_contact_number',
        'delivery_lat', 'delivery_lng',
        'origin_port_name', 'origin_port_code', 'origin_port_category',
        'destination_port_name', 'destination_port_code', 'destination_port_category',
        'vessel_name', 'vessel_imo_number', 'voyage_number', 'bill_of_lading',
        'vessel_tracking_ref', 'vessel_tracking_data', 'eta', 'etd', 'last_vessel_tracked_at',
        'dispatch_date', 'trip_start_time', 'expected_delivery_date',
        'actual_delivery_date', 'trip_end_time',
        'epod_status', 'epod_confirmed_at', 'epod_confirmed_by_id',
        'destination_confirmed_by_id', 'destination_confirmation_notes', 'destination_confirmed_at',
    ];

    protected $casts = [
        'status' => TripStatus::class,
        'trip_type' => TripType::class,
        'transport_mode' => TripTransportationMode::class,
        'vessel_tracking_data' => 'array',
        'eta' => 'datetime',
        'etd' => 'datetime',
        'last_vessel_tracked_at' => 'datetime',
        'trip_start_time' => 'datetime',
        'trip_end_time' => 'datetime',
        'epod_confirmed_at' => 'datetime',
        'destination_confirmed_at' => 'datetime',
        'dispatch_date' => 'date',
        'invoice_date' => 'date',
        'seal_issue_date' => 'date',
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
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function seal(): BelongsTo
    {
        return $this->belongsTo(Seal::class, 'seal_id');
    }

    public function route(): BelongsTo
    {
        return $this->belongsTo(CustomerRoute::class, 'route_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(TripDocument::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(TripEvent::class)->orderBy('created_at');
    }

    public function isLocked(): bool
    {
        return $this->status === TripStatus::Completed;
    }
}
