<?php

namespace App\Models;

use App\Enums\TripDocType;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TripDocument extends Model
{
    public $timestamps = false;
    protected $fillable = ['trip_id', 'customer_id', 'uploaded_by_id', 'doc_type', 'file_name', 'url'];
    protected $casts = ['doc_type' => TripDocType::class, 'created_at' => 'datetime'];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function trip(): BelongsTo
    {
        return $this->belongsTo(Trip::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}
