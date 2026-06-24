<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use InvalidArgumentException;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PatientDepositStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\DepositApplication;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Services\DepositApplicationService;
use Modules\Billing\Services\DepositRecordingService;
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Models\Branch;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class DepositApplicationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_apply_deposit_pays_invoice_and_depletes_deposit(): void
    {
        [$patient, $branch, $invoice] = $this->seedIssuedInvoice('100.00');

        $deposit = app(DepositRecordingService::class)->record(
            patientId: $patient->id,
            branchId: (string) $branch->id,
            amount: '100.00',
            method: PaymentMethod::Cash,
        );

        app(DepositApplicationService::class)->apply(
            deposit: $deposit,
            invoice: $invoice->fresh(['lines']),
            amount: '100.00',
        );

        $invoice->refresh();
        $deposit->refresh();

        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame(PatientDepositStatus::Depleted, $deposit->status);
        $this->assertSame('0.00', (string) $deposit->unallocated_balance);
        $this->assertSame(1, DepositApplication::query()->where('patient_deposit_id', $deposit->id)->count());
    }

    public function test_partial_apply_leaves_remaining_deposit_balance(): void
    {
        [$patient, $branch, $invoice] = $this->seedIssuedInvoice('50.00');

        $deposit = app(DepositRecordingService::class)->record(
            patientId: $patient->id,
            branchId: (string) $branch->id,
            amount: '200.00',
            method: PaymentMethod::Cash,
        );

        app(DepositApplicationService::class)->apply(
            deposit: $deposit,
            invoice: $invoice->fresh(['lines']),
            amount: '50.00',
        );

        $deposit->refresh();
        $this->assertTrue($deposit->isActive());
        $this->assertSame('150.00', (string) $deposit->unallocated_balance);
    }

    public function test_cannot_over_apply_deposit(): void
    {
        [$patient, $branch, $invoice] = $this->seedIssuedInvoice('100.00');

        $deposit = app(DepositRecordingService::class)->record(
            patientId: $patient->id,
            branchId: (string) $branch->id,
            amount: '30.00',
            method: PaymentMethod::Cash,
        );

        $this->expectException(InvalidArgumentException::class);

        app(DepositApplicationService::class)->apply(
            deposit: $deposit,
            invoice: $invoice->fresh(['lines']),
            amount: '100.00',
        );
    }

    /**
     * @return array{0: Patient, 1: Branch, 2: Invoice}
     */
    private function seedIssuedInvoice(string $total): array
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $invoice = null;

        Invoice::withoutEvents(function () use ($branch, $patient, $total, &$invoice) {
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
                'billable_type' => null,
                'billable_id' => null,
                'description' => 'Fee',
                'quantity' => 1,
                'unit_price' => $total,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'line_total' => $total,
                'amount_paid' => 0,
                'line_status' => InvoiceLineStatus::Unpaid,
                'patient_responsibility_amount' => $total,
            ]);

            app(InvoiceIssuanceService::class)->issue($invoice->fresh(['lines']));
        });

        return [$patient, $branch, $invoice->fresh(['lines'])];
    }
}
