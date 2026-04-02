<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerWalletTransaction extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'wallet_id', 'customer_id', 'type', 'amount',
        'reference_type', 'reference_id', 'balance_after',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'balance_after' => 'decimal:2',
        'created_at' => 'datetime',
    ];

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(CustomerWallet::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
