<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Models\Payment;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class PaymentRevenueScopeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_revenue_scope_returns_only_payment_type_rows(): void
    {
        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));

        $row = fn (PaymentType $type) => Payment::create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'method' => PaymentMethod::Cash,
            'gateway' => 'cash',
            'type' => $type,
            'amount' => '100.00',
            'currency' => 'GHS',
            'provider_transaction_id' => 'txn-'.Str::uuid(),
            'received_at' => now(),
            'metadata' => [],
        ]);

        $row(PaymentType::Payment);
        $row(PaymentType::Deposit);
        $row(PaymentType::WriteOff);
        $row(PaymentType::Refund);

        $ids = Payment::query()->revenue()->pluck('id');

        $this->assertCount(1, $ids);
        $this->assertSame('100.00', Payment::query()->revenue()->firstOrFail()->amount);
    }
}
