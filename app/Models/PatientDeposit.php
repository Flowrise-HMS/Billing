<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Billing\Database\Factories\PatientDepositFactory;
use Modules\Billing\Enums\PatientDepositStatus;
use Modules\Core\Models\Branch;
use Modules\Core\Models\CoreUser;
use Modules\Patient\Models\Patient;

/**
 * @property PatientDepositStatus $status
 * @property string $unallocated_balance
 */
class PatientDeposit extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'patient_id',
        'branch_id',
        'payment_id',
        'amount',
        'unallocated_balance',
        'currency',
        'status',
        'recorded_by',
    ];

    protected $casts = [
        'status' => PatientDepositStatus::class,
        'amount' => 'decimal:2',
        'unallocated_balance' => 'decimal:2',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    public function recorder(): BelongsTo
    {
        return $this->belongsTo(CoreUser::class, 'recorded_by');
    }

    public function applications(): HasMany
    {
        return $this->hasMany(DepositApplication::class);
    }

    protected static function newFactory(): Factory
    {
        return PatientDepositFactory::new();
    }

    public function isActive(): bool
    {
        return $this->status === PatientDepositStatus::Active
            && bccomp((string) $this->unallocated_balance, '0', 2) > 0;
    }

    public function remainingAmount(): string
    {
        return (string) $this->unallocated_balance;
    }
}
