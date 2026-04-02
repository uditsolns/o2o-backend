<?php

namespace App\Models;

use App\Enums\PortCategory;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPort extends Model
{
    public $timestamps = false;
    protected $fillable = [
        'customer_id', 'port_id', 'port_category', 'name', 'code',
        'lat', 'lng', 'geo_fence_radius', 'is_active',
    ];
    protected $casts = ['port_category' => PortCategory::class, 'is_active' => 'boolean'];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function port(): BelongsTo
    {
        return $this->belongsTo(Port::class);
    }
}
