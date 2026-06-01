<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerOnboardingHistory extends Model
{
    protected $table = 'customer_onboarding_history';

    public $timestamps = false;

    protected $fillable = [
        'customer_id', 'from_status', 'to_status',
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

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}
