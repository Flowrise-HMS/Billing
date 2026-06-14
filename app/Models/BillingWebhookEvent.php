<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BillingWebhookEvent extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'driver',
        'idempotency_key',
        'payment_id',
        'processed_at',
        'metadata',
    ];

    protected $casts = [
        'processed_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }
}
