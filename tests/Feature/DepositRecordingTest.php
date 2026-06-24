<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\PatientDeposit;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\DepositBalanceService;
use Modules\Billing\Services\DepositRecordingService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class DepositRecordingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_record_deposit_creates_payment_and_patient_deposit(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $deposit = app(DepositRecordingService::class)->record(
            patientId: $patient->id,
            branchId: (string) $branch->id,
            amount: '250.00',
            method: PaymentMethod::Cash,
            reference: 'DEP-001',
            recordedBy: null,
        );

        $this->assertInstanceOf(PatientDeposit::class, $deposit);
        $this->assertSame('250.00', (string) $deposit->amount);
        $this->assertSame('250.00', (string) $deposit->unallocated_balance);
        $this->assertTrue($deposit->isActive());

        $payment = Payment::query()->find($deposit->payment_id);
        $this->assertNotNull($payment);
        $this->assertSame('250.00', (string) $payment->amount);

        $balance = app(DepositBalanceService::class)->unallocatedBalanceForPatient($patient->id);
        $this->assertSame('250.00', $balance);
    }
}
