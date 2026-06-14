<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentIntent;
use Modules\Core\Models\Branch;

class PaymentIntentFactory extends Factory
{
    protected $model = PaymentIntent::class;

    public function definition(): array
    {
        return [
            'invoice_id' => Invoice::factory(),
            'branch_id' => Branch::factory(),
            'gateway' => fake()->randomElement(['stripe', 'paystack', 'hubtel']),
            'status' => PaymentIntentStatus::PENDING,
            'amount' => fake()->randomFloat(2, 10, 5000),
            'currency' => 'GHS',
            'expires_at' => now()->addHours(2),
        ];
    }
}
