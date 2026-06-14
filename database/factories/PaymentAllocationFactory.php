<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PaymentAllocation;

class PaymentAllocationFactory extends Factory
{
    protected $model = PaymentAllocation::class;

    public function definition(): array
    {
        return [
            'payment_id' => Payment::factory(),
            'invoice_line_id' => InvoiceLine::factory(),
            'amount' => fake()->randomFloat(2, 5, 1000),
        ];
    }
}
