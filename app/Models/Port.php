<?php

namespace App\Models;

use App\Enums\PortCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Port extends Model
{
    protected $fillable = [
        'name', 'code', 'city', 'country', 'port_category',
        'sepio_id', 'lat', 'lng', 'geo_fence_radius', 'is_active', 'created_by_id',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'port_category' => PortCategory::class,
    ];

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function scopeActive($q)
    {
        return $q->where('is_active', true);
    }
}
