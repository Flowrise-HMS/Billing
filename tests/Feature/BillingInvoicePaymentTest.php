<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Mail;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Services\InvoiceAllocationBuilder;
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Billing\Services\PaymentRecordingService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class BillingInvoicePaymentTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();

        foreach (['Patient', 'Clinical', 'Appointment', 'Billing'] as $module) {
            $this->artisan('module:migrate', ['module' => $module, '--force' => true]);
        }
    }

    public function test_issue_invoice_and_record_cash_payment(): void
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
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'status' => InvoiceStatus::Draft,
                'invoice_type' => InvoiceType::Standalone,
                'currency' => 'GHS',
            ]);

            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'billable_type' => null,
                'billable_id' => null,
                'description' => 'Consultation',
                'quantity' => 1,
                'unit_price' => 100,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'line_total' => 100,
                'amount_paid' => 0,
                'line_status' => InvoiceLineStatus::Unpaid,
                'patient_responsibility_amount' => 100,
            ]);

            $invoice = $invoice->fresh(['lines']);
            app(InvoiceIssuanceService::class)->issue($invoice);

            $invoice->refresh();
            $this->assertSame(InvoiceStatus::Issued, $invoice->status);

            $allocations = app(InvoiceAllocationBuilder::class)->allocateAmountAcrossUnpaidLines($invoice->fresh(['lines']), '100');
            $this->assertNotEmpty($allocations);

            app(PaymentRecordingService::class)->record(
                allocations: $allocations,
                method: PaymentMethod::Cash,
                gateway: 'cash',
                currency: 'GHS',
                patientId: $patient->id,
                branchId: (string) $branch->id,
                recordedBy: null,
            );

            $invoice->refresh();
            $this->assertSame(InvoiceStatus::Paid, $invoice->status);
            $this->assertEquals('100.00', (string) $invoice->amount_paid);
        });
    }
}
