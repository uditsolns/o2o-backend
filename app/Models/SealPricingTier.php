<?php

namespace App\Models;

use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SealPricingTier extends Model
{
    protected $fillable = [
        'customer_id', 'min_quantity', 'max_quantity', 'price_per_seal', 'is_active', 'created_by_id',
    ];
    protected $casts = ['is_active' => 'boolean', 'price_per_seal' => 'decimal:2'];
    protected $guarded = ['max_quantity_key'];

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

    public function covers(int $qty): bool
    {
        return $qty >= $this->min_quantity
            && (is_null($this->max_quantity) || $qty <= $this->max_quantity);
    }
}
