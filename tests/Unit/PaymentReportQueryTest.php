<?php

namespace Modules\Billing\Tests\Unit;

use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Data\BillingReportCriteria;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Payment;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class PaymentReportQueryTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_query_for_report_scopes_by_date_range(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');

        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));

        Payment::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'method' => PaymentMethod::Cash,
            'gateway' => 'cash',
            'amount' => 100,
            'currency' => 'GHS',
            'provider_transaction_id' => 'in-range',
            'received_at' => '2026-05-10 10:00:00',
        ]);

        Payment::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'method' => PaymentMethod::Cash,
            'gateway' => 'cash',
            'amount' => 50,
            'currency' => 'GHS',
            'provider_transaction_id' => 'out-of-range',
            'received_at' => '2026-04-01 10:00:00',
        ]);

        $criteria = BillingReportCriteria::fromRequest([
            'preset' => 'custom',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
        ]);

        $ids = Payment::queryForReport($criteria)->pluck('provider_transaction_id')->all();

        $this->assertSame(['in-range'], $ids);
    }

    public function test_query_for_report_scopes_by_branch_and_payment_method(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');

        $branchA = BranchFactory::new()->create(['name' => 'Main']);
        $branchB = BranchFactory::new()->create(['name' => 'Annex']);
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branchA->id]));

        Payment::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branchA->id,
            'method' => PaymentMethod::Cash,
            'gateway' => 'cash',
            'amount' => 100,
            'currency' => 'GHS',
            'provider_transaction_id' => 'branch-a-cash',
            'received_at' => '2026-05-10 10:00:00',
        ]);

        Payment::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branchB->id,
            'method' => PaymentMethod::Cash,
            'gateway' => 'cash',
            'amount' => 80,
            'currency' => 'GHS',
            'provider_transaction_id' => 'branch-b-cash',
            'received_at' => '2026-05-10 11:00:00',
        ]);

        Payment::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branchA->id,
            'method' => PaymentMethod::Card,
            'gateway' => 'card',
            'amount' => 60,
            'currency' => 'GHS',
            'provider_transaction_id' => 'branch-a-card',
            'received_at' => '2026-05-10 12:00:00',
        ]);

        $criteria = BillingReportCriteria::fromRequest([
            'preset' => 'custom',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
            'branch_id' => (string) $branchA->id,
            'payment_method' => PaymentMethod::Cash->value,
        ]);

        $ids = Payment::queryForReportListing($criteria)->pluck('provider_transaction_id')->all();

        $this->assertSame(['branch-a-cash'], $ids);
    }

    public function test_query_for_report_listing_eager_loads_recorder(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');

        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));
        $cashier = User::factory()->create(['name' => 'Report Cashier']);

        Payment::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'method' => PaymentMethod::Cash,
            'gateway' => 'cash',
            'amount' => 40,
            'currency' => 'GHS',
            'provider_transaction_id' => 'with-cashier',
            'received_at' => '2026-05-10 10:00:00',
            'recorded_by' => $cashier->id,
        ]);

        $criteria = BillingReportCriteria::fromRequest([
            'preset' => 'custom',
            'start_date' => '2026-05-01',
            'end_date' => '2026-05-31',
        ]);

        $payment = Payment::queryForReportListing($criteria)->first();

        $this->assertNotNull($payment);
        $this->assertTrue($payment->relationLoaded('recorder'));
        $this->assertSame('Report Cashier', $payment->recorder?->name);
    }
}
