<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerSepioHistory extends Model
{
    protected $table = 'customer_sepio_history';

    public $timestamps = false;

    protected $fillable = [
        'customer_id', 'from_status', 'to_status',
        'triggered_by_type', 'triggered_by_id',
        'remarks', 'rejected_documents',
    ];

    protected $casts = [
        'rejected_documents' => 'array',
        'created_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($model) => $model->created_at ??= now());
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by_id');
    }
}
