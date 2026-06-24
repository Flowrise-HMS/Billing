<?php

namespace Modules\Billing\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Enums\PatientDepositStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Models\PatientDeposit;
use Modules\Billing\Models\Payment;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;

class PatientDepositFactory extends Factory
{
    protected $model = PatientDeposit::class;

    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 50, 1000);

        return [
            'patient_id' => Patient::factory(),
            'branch_id' => Branch::factory(),
            'payment_id' => Payment::factory()->state([
                'type' => PaymentType::Deposit,
                'method' => PaymentMethod::Cash,
                'gateway' => 'cash',
            ]),
            'amount' => $amount,
            'unallocated_balance' => $amount,
            'currency' => 'GHS',
            'status' => PatientDepositStatus::Active,
            'recorded_by' => User::factory(),
        ];
    }
}
