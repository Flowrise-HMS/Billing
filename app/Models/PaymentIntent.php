<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Core\Models\Branch;

class PaymentIntent extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'invoice_id',
        'branch_id',
        'gateway',
        'status',
        'amount',
        'currency',
        'line_ids',
        'client_reference',
        'provider_reference',
        'checkout_url',
        'raw_response',
        'metadata',
        'expires_at',
    ];

    protected $casts = [
        'status' => PaymentIntentStatus::class,
        'amount' => 'decimal:2',
        'line_ids' => 'array',
        'raw_response' => 'array',
        'metadata' => 'array',
        'expires_at' => 'datetime',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
