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
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Billing\Services\PaymentRecordingService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class WriteOffLineTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_write_off_zeros_line_balance(): void
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
                'description' => 'Uncollectible',
                'quantity' => 1,
                'unit_price' => 75,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'line_total' => 75,
                'amount_paid' => 0,
                'line_status' => InvoiceLineStatus::Unpaid,
                'patient_responsibility_amount' => 75,
            ]);

            app(InvoiceIssuanceService::class)->issue($invoice->fresh(['lines']));
        });

        app(PaymentRecordingService::class)->record(
            allocations: [(string) $line->id => '75.00'],
            method: PaymentMethod::Gateway,
            gateway: 'write_off',
            currency: 'GHS',
            patientId: $patient->id,
            branchId: (string) $branch->id,
            recordedBy: null,
            type: PaymentType::WriteOff,
        );

        $line->refresh();
        $this->assertSame('75.00', (string) $line->amount_paid);
        $this->assertSame(InvoiceLineStatus::Paid, $line->line_status);
    }
}
