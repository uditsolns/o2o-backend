<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SealOrderHistory extends Model
{
    protected $table = 'seal_order_history';

    public $timestamps = false;

    protected $fillable = [
        'order_id', 'from_status', 'to_status',
        'actor_type', 'actor_id', 'remarks', 'remarks_file_url',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    protected static function boot(): void
    {
        parent::boot();
        static::creating(fn($model) => $model->created_at ??= now());
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(SealOrder::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
