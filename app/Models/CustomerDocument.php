<?php

namespace App\Models;

use App\Enums\CustomerDocType;
use App\Scopes\TenantScope;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerDocument extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'customer_id', 'uploaded_by_id', 'doc_type',
        'doc_number', 'file_name', 'url', 'sepio_file_name',
    ];

    protected $casts = [
        'doc_type' => CustomerDocType::class,
        'created_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::addGlobalScope(new TenantScope());
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by_id');
    }
}
