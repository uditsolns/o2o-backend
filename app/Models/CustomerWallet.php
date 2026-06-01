<?php
// app/Models/CustomerWallet.php
namespace App\Models;

use App\Enums\WalletCoastingType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\{BelongsTo, HasMany};

class CustomerWallet extends Model
{
    protected $fillable = [
        'customer_id', 'costing_type', 'credit_period', 'credit_capping', 'credit_used',
        'freight_rate_per_seal', 'cost_balance', 'low_balance_threshold', 'created_by_id',
    ];

    protected $casts = [
        'costing_type' => WalletCoastingType::class,
        'credit_capping' => 'decimal:2',
        'credit_used' => 'decimal:2',
        'cost_balance' => 'decimal:2',
        'low_balance_threshold' => 'decimal:2',
    ];

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(CustomerWalletTransaction::class, 'wallet_id');
    }

    public function pricingTiers(): HasMany
    {
        return $this->hasMany(SealPricingTier::class, 'customer_id', 'customer_id');
    }

    public function tripPricingRules(): HasMany
    {
        return $this->hasMany(TripPricingRule::class, 'customer_id', 'customer_id');
    }

    public function hasSufficientBalance(float $amount): bool
    {
        return $this->cost_balance >= $amount;
    }

    public function withinCreditLimit(float $additionalAmount): bool
    {
        if (is_null($this->credit_capping)) return true;
        return ($this->credit_used + $additionalAmount) <= $this->credit_capping;
    }
}
