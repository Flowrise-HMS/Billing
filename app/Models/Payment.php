<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Core\Contracts\ProvidesClientIdentity;
use Modules\Core\Models\Branch;
use Modules\Core\Support\ClientIdentity;
use Modules\Core\Support\ClientIdentityResolver;
use Modules\Patient\Models\Patient;

class Payment extends Model implements ProvidesClientIdentity
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

    public function patientDeposit(): HasOne
    {
        return $this->hasOne(PatientDeposit::class);
    }

    public function clientIdentity(): ClientIdentity
    {
        if ($this->patient_id !== null) {
            return ClientIdentityResolver::resolve(
                patientFullName: $this->patient?->full_name,
                patientMrn: $this->patient?->mrn,
            );
        }

        $invoice = $this->allocations->first()?->invoiceLine?->invoice;
        if ($invoice !== null) {
            return $invoice->clientIdentity();
        }

        return ClientIdentityResolver::resolve();
    }
}
