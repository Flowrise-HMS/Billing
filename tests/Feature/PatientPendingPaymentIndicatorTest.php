<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Services\DepositRecordingService;
use Modules\Billing\Services\PatientBalanceQueryService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class PatientPendingPaymentIndicatorTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_open_balance_reflects_unpaid_issued_invoice(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        Invoice::withoutEvents(function () use ($branch, $patient) {
            $invoice = Invoice::query()->withoutGlobalScopes()->create([
                'organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
                'status' => InvoiceStatus::Issued,
                'invoice_type' => InvoiceType::Standalone,
                'currency' => 'GHS',
                'issued_at' => now(),
                'total' => '150.00',
                'amount_paid' => '0',
            ]);

            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'billable_type' => null,
                'billable_id' => null,
                'description' => 'Fee',
                'quantity' => 1,
                'unit_price' => 150,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'line_total' => 150,
                'amount_paid' => 0,
                'line_status' => InvoiceLineStatus::Unpaid,
                'patient_responsibility_amount' => 150,
            ]);
        });

        $balance = app(PatientBalanceQueryService::class)->openBalanceForPatient($patient->id);

        $this->assertSame('150.00', $balance);
    }

    public function test_deposit_balance_for_patient_reflects_unallocated_credit(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        app(DepositRecordingService::class)->record(
            patientId: $patient->id,
            branchId: (string) $branch->id,
            amount: '80.00',
            method: PaymentMethod::Cash,
        );

        $depositBalance = app(PatientBalanceQueryService::class)->depositBalanceForPatient($patient->id);
        $openBalance = app(PatientBalanceQueryService::class)->openBalanceForPatient($patient->id);

        $this->assertSame('80.00', $depositBalance);
        $this->assertSame('0', $openBalance);
    }
}
