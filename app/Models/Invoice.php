<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Modules\Appointment\Models\Appointment;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Clinical\Models\Encounter;
use Modules\Core\Models\BaseModel;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Organization;
use Modules\Patient\Models\Patient;

class Invoice extends BaseModel
{
    use HasUuids;

    protected $keyType = 'string';

    protected $fillable = [
        'organization_id',
        'branch_id',
        'patient_id',
        'encounter_id',
        'appointment_id',
        'invoice_number',
        'status',
        'invoice_type',
        'currency',
        'issued_at',
        'due_at',
        'encounter_discharged_at',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'amount_paid',
        'lock_version',
        'guest_name',
        'guest_phone',
        'guest_email',
        'metadata',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => InvoiceStatus::class,
        'invoice_type' => InvoiceType::class,
        'issued_at' => 'datetime',
        'due_at' => 'datetime',
        'encounter_discharged_at' => 'datetime',
        'subtotal' => 'decimal:2',
        'tax_total' => 'decimal:2',
        'discount_total' => 'decimal:2',
        'total' => 'decimal:2',
        'amount_paid' => 'decimal:2',
        'lock_version' => 'integer',
        'metadata' => 'array',
    ];

    public static function generateInvoiceNumber(): string
    {
        $prefix = 'INV';
        $date = now()->format('Ymd');
        $sequence = static::withoutGlobalScopes()->whereDate('created_at', today())->count() + 1;

        return sprintf('%s-%s-%05d', $prefix, $date, $sequence);
    }

    public function organization(): BelongsTo
    {
        return $this->belongsTo(Organization::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    public function paymentIntents(): HasMany
    {
        return $this->hasMany(PaymentIntent::class);
    }

    public function balanceDue(): string
    {
        return bcsub((string) $this->total, (string) $this->amount_paid, 2);
    }

    public function isDraft(): bool
    {
        return $this->status === InvoiceStatus::Draft;
    }
}
