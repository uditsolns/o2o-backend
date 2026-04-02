<?php

namespace App\Models;

use App\Enums\SealOrderStatus;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class SealOrder extends Model
{
    const CREATED_AT = 'ordered_at';
    const UPDATED_AT = 'updated_at';

    protected $fillable = [
        'customer_id', 'ordered_by_id', 'order_ref', 'quantity',
        'unit_price', 'seal_cost', 'freight_amount', 'gst_amount', 'total_amount',
        'payment_type', 'billing_location_id', 'shipping_location_id',
        'receiver_name', 'receiver_contact', 'status',
        'il_approved_by', 'il_approved_at', 'il_remarks', 'il_remark_file_url',
        'sepio_order_id', 'sepio_billing_address_id', 'sepio_shipping_address_id', 'sepio_order_ports',
        'courier_name', 'courier_docket_number', 'seals_dispatched_at', 'seals_delivered_at',
    ];

    protected $casts = [
        'status' => SealOrderStatus::class,
        'sepio_order_ports' => 'array',
        'il_approved_at' => 'datetime',
        'seals_dispatched_at' => 'datetime',
        'seals_delivered_at' => 'datetime',
        'unit_price' => 'decimal:2',
        'seal_cost' => 'decimal:2',
        'freight_amount' => 'decimal:2',
        'gst_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by_id');
    }

    public function billingLocation(): BelongsTo
    {
        return $this->belongsTo(CustomerLocation::class, 'billing_location_id');
    }

    public function shippingLocation(): BelongsTo
    {
        return $this->belongsTo(CustomerLocation::class, 'shipping_location_id');
    }

    public function ilApprovedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'il_approved_by');
    }

    public function seals(): HasMany
    {
        return $this->hasMany(Seal::class);
    }
}
