<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\PaymentAllocation;
use Modules\Billing\Services\InvoiceAllocationBuilder;
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Billing\Services\PaymentRecordingService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class BillingDeskLineItemsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Appointment', 'Billing']);
    }

    public function test_line_items_query_returns_non_void_lines_for_invoice(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $invoice = Invoice::withoutEvents(function () use ($branch, $patient) {
            $invoice = Invoice::query()->withoutGlobalScopes()->create([
                'organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
                'status' => InvoiceStatus::Draft,
                'invoice_type' => InvoiceType::Standalone,
                'currency' => 'GHS',
            ]);

            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'description' => 'Consultation',
                'quantity' => 1,
                'unit_price' => 100,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'line_total' => 100,
                'amount_paid' => 0,
                'line_status' => InvoiceLineStatus::Unpaid,
            ]);

            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'description' => 'Void line',
                'quantity' => 1,
                'unit_price' => 50,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'line_total' => 50,
                'amount_paid' => 0,
                'line_status' => InvoiceLineStatus::Void,
            ]);

            return $invoice;
        });

        $lines = InvoiceLine::query()
            ->where('invoice_id', $invoice->id)
            ->where('line_status', '!=', InvoiceLineStatus::Void)
            ->orderBy('id')
            ->get();

        $this->assertCount(1, $lines);
        $this->assertSame('Consultation', $lines->first()->description);
    }

    public function test_line_with_payment_allocation_resolves_receipt_url(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        [$line, $payment] = Invoice::withoutEvents(function () use ($branch, $patient) {
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
                'description' => 'Lab test',
                'quantity' => 1,
                'unit_price' => 75,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'line_total' => 75,
                'amount_paid' => 0,
                'line_status' => InvoiceLineStatus::Unpaid,
            ]);

            app(InvoiceIssuanceService::class)->issue($invoice->fresh(['lines']));
            $allocations = app(InvoiceAllocationBuilder::class)->allocateAmountAcrossUnpaidLines(
                $invoice->fresh(['lines']),
                '75'
            );

            $payment = app(PaymentRecordingService::class)->record(
                allocations: $allocations,
                method: PaymentMethod::Cash,
                gateway: 'cash',
                currency: 'GHS',
                patientId: $patient->id,
                branchId: (string) $branch->id,
                recordedBy: null,
            );

            return [$line->fresh(), $payment];
        });

        $line = $line->fresh(['paymentAllocations.payment']);

        $latestPaymentId = $line->paymentAllocations
            ->sortByDesc(fn (PaymentAllocation $allocation) => $allocation->payment?->created_at)
            ->first()?->payment_id;

        $this->assertSame((string) $payment->id, (string) $latestPaymentId);

        $url = route('billing.payments.receipt', $latestPaymentId).'?line_id='.$line->id;

        $this->assertStringContainsString((string) $payment->id, $url);
        $this->assertStringContainsString('line_id='.$line->id, $url);
        $this->assertSame('0.00', $line->remainingAmount());
        $this->assertSame('75.00', (string) $line->fresh()->amount_paid);
    }
}
