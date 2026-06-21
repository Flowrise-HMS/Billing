<?php

namespace Modules\Billing\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Payment;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;

class PaymentFactory extends Factory
{
    protected $model = Payment::class;

    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'branch_id' => Branch::factory(),
            'method' => PaymentMethod::Cash,
            'amount' => fake()->randomFloat(2, 10, 5000),
            'currency' => 'GHS',
            'provider_transaction_id' => fake()->uuid(),
            'received_at' => now(),
            'recorded_by' => User::factory(),
        ];
    }
}
