<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Models\BillingWebhookEvent;
use Modules\Billing\Models\Payment;

class BillingWebhookEventFactory extends Factory
{
    protected $model = BillingWebhookEvent::class;

    public function definition(): array
    {
        return [
            'driver' => fake()->randomElement(['stripe', 'paystack', 'hubtel']),
            'idempotency_key' => fake()->uuid(),
            'payment_id' => Payment::factory(),
            'processed_at' => now(),
            'metadata' => ['event_type' => 'charge.completed'],
        ];
    }
}
