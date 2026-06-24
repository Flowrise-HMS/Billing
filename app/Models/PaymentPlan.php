<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Billing\Enums\PaymentPlanStatus;

class PaymentPlan extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'invoice_id',
        'total_amount',
        'down_payment',
        'installment_count',
        'frequency_days',
        'status',
        'start_date',
        'notes',
        'created_by',
    ];

    protected $casts = [
        'status' => PaymentPlanStatus::class,
        'total_amount' => 'decimal:2',
        'down_payment' => 'decimal:2',
        'installment_count' => 'integer',
        'frequency_days' => 'integer',
        'start_date' => 'date',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function installments(): HasMany
    {
        return $this->hasMany(PaymentPlanInstallment::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(\Modules\Core\Models\User::class, 'created_by');
    }

    public function remainingBalance(): string
    {
        $paid = '0';
        foreach ($this->installments as $installment) {
            $paid = bcadd($paid, (string) $installment->paid_amount, 2);
        }

        return bcsub((string) $this->total_amount, $paid, 2);
    }
}
