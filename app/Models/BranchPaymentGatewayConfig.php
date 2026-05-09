<?php

namespace Modules\Billing\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Core\Models\Branch;

class BranchPaymentGatewayConfig extends Model
{
    use HasUuids;

    protected $keyType = 'string';

    protected $table = 'branch_payment_gateway_configs';

    protected $fillable = [
        'branch_id',
        'driver',
        'display_name',
        'public_key',
        'secret_key',
        'webhook_secret',
        'is_enabled',
        'test_mode',
        'metadata',
    ];

    protected $casts = [
        'is_enabled' => 'boolean',
        'test_mode' => 'boolean',
        'metadata' => 'array',
        'secret_key' => 'encrypted',
        'webhook_secret' => 'encrypted',
    ];

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
