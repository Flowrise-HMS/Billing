<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Billing\Enums\PaymentPlanInstallmentStatus;

/**
 * @property string $amount
 * @property string $paid_amount
 */
class PaymentPlanInstallment extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'payment_plan_id',
        'installment_number',
        'due_date',
        'amount',
        'paid_amount',
        'status',
        'paid_at',
    ];

    protected $casts = [
        'status' => PaymentPlanInstallmentStatus::class,
        'amount' => 'decimal:2',
        'paid_amount' => 'decimal:2',
        'due_date' => 'date',
        'paid_at' => 'datetime',
    ];

    public function paymentPlan(): BelongsTo
    {
        return $this->belongsTo(PaymentPlan::class);
    }

    public function remainingAmount(): string
    {
        return bcsub((string) $this->amount, (string) $this->paid_amount, 2);
    }

    public function isFullyPaid(): bool
    {
        return bccomp($this->remainingAmount(), '0', 2) <= 0;
    }
}
