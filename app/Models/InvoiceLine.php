<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Core\Models\Service;

class InvoiceLine extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'invoice_id',
        'billable_type',
        'billable_id',
        'service_id',
        'description',
        'quantity',
        'unit_price',
        'discount_amount',
        'tax_amount',
        'line_total',
        'amount_paid',
        'line_status',
        'original_unit_price',
        'adjustment_reason',
        'patient_responsibility_amount',
        'insurance_expected_amount',
        'claim_line_id',
        'payer_snapshot',
        'metadata',
        'unit_id',
        'unit_label_snapshot',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'unit_price' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'line_total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'line_status' => InvoiceLineStatus::class,
        'original_unit_price' => 'decimal:2',
        'patient_responsibility_amount' => 'decimal:2',
        'insurance_expected_amount' => 'decimal:2',
        'payer_snapshot' => 'array',
        'metadata' => 'array',
    ];

    protected static function booted(): void
    {
        static::saving(function (InvoiceLine $line) {
            $qty = max(1, (int) $line->quantity);
            $line->quantity = $qty;
            $subtotal = bcmul((string) $line->unit_price, (string) $qty, 2);
            $afterDiscount = bcsub($subtotal, (string) ($line->discount_amount ?? 0), 2);
            $line->line_total = bcadd($afterDiscount, (string) ($line->tax_amount ?? 0), 2);
            if ($line->line_status !== InvoiceLineStatus::Void && $line->patient_responsibility_amount === null) {
                $line->patient_responsibility_amount = $line->line_total;
            }
        });
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function billable(): MorphTo
    {
        return $this->morphTo();
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function paymentAllocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function remainingAmount(): string
    {
        return bcsub((string) $this->line_total, (string) $this->amount_paid, 2);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\Unit::class);
    }
}
