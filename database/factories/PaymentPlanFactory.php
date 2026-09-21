<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Enums\PaymentPlanStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentPlan;

class PaymentPlanFactory extends Factory
{
    protected $model = PaymentPlan::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'total_amount' => '200.00',
            'down_payment' => '20.00',
            'installment_count' => 4,
            'frequency_days' => 14,
            'status' => PaymentPlanStatus::Active,
            'start_date' => now()->toDateString(),
        ];
    }
}
