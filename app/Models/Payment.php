<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;

class Payment extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'patient_id',
        'branch_id',
        'method',
        'gateway',
        'type',
        'amount',
        'currency',
        'provider_transaction_id',
        'received_at',
        'recorded_by',
        'metadata',
    ];

    protected $casts = [
        'method' => PaymentMethod::class,
        'type' => PaymentType::class,
        'amount' => 'decimal:2',
        'received_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function allocations(): HasMany
    {
        return $this->hasMany(PaymentAllocation::class);
    }

    public function patientDeposit(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(PatientDeposit::class);
    }
}
