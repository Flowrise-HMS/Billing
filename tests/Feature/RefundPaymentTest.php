<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Services\InvoiceAllocationBuilder;
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Billing\Services\PaymentRecordingService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class RefundPaymentTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_refund_reduces_line_balances(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $line = null;

        Invoice::withoutEvents(function () use ($branch, $patient, &$line) {
            $invoice = Invoice::query()->withoutGlobalScopes()->create([
                'organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
                'status' => InvoiceStatus::Draft,
                'invoice_type' => InvoiceType::Standalone,
                'currency' => 'GHS',
            ]);

            $line = InvoiceLine::query()->create([
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

            app(InvoiceIssuanceService::class)->issue($invoice->fresh(['lines']));

            $allocations = app(InvoiceAllocationBuilder::class)
                ->allocateAmountAcrossUnpaidLines($invoice->fresh(['lines']), '100');

            app(PaymentRecordingService::class)->record(
                allocations: $allocations,
                method: PaymentMethod::Cash,
                gateway: 'cash',
                currency: 'GHS',
                patientId: $patient->id,
                branchId: (string) $branch->id,
                recordedBy: null,
            );
        });

        $line->refresh();
        $this->assertSame('100.00', (string) $line->amount_paid);

        app(PaymentRecordingService::class)->record(
            allocations: [(string) $line->id => '-50.00'],
            method: PaymentMethod::Gateway,
            gateway: 'cash',
            currency: 'GHS',
            patientId: $patient->id,
            branchId: (string) $branch->id,
            recordedBy: null,
            type: PaymentType::Refund,
        );

        $line->refresh();
        $this->assertSame('50.00', (string) $line->amount_paid);
    }
}
