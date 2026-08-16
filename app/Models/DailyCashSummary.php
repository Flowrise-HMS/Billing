<?php

namespace Modules\Billing\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Billing\Enums\DailyCashSummaryStatus;
use Modules\Core\Models\Branch;

class DailyCashSummary extends Model
{
    use HasFactory, HasUuids;

    protected $keyType = 'string';

    protected $attributes = [
        'status' => DailyCashSummaryStatus::Open->value,
        'opening_cash' => '0.00',
        'change_given' => '0.00',
        'expected_closing' => '0.00',
        'variance' => '0.00',
    ];

    protected $fillable = [
        'branch_id',
        'cashier_id',
        'summary_date',
        'opening_cash',
        'change_given',
        'counted_closing',
        'expected_closing',
        'variance',
        'status',
        'finalized_at',
        'finalized_by',
        'reopened_at',
        'reopened_by',
        'notes',
    ];

    protected $casts = [
        'summary_date' => 'date',
        'opening_cash' => 'decimal:2',
        'change_given' => 'decimal:2',
        'counted_closing' => 'decimal:2',
        'expected_closing' => 'decimal:2',
        'variance' => 'decimal:2',
        'status' => DailyCashSummaryStatus::class,
        'finalized_at' => 'datetime',
        'reopened_at' => 'datetime',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function finalizedByUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'finalized_by');
    }
}
