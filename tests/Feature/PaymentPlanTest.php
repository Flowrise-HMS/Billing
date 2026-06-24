<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentPlanStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\PaymentPlan;
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Billing\Services\PaymentPlanService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class PaymentPlanTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Appointment', 'Billing']);
    }

    public function test_create_payment_plan_generates_installments(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        Invoice::withoutEvents(function () use ($branch, $patient) {
            $invoice = $this->createIssuedInvoice($branch, $patient, 600);

            $service = app(PaymentPlanService::class);
            $plan = $service->createPlan(
                invoice: $invoice,
                installmentCount: 3,
                frequencyDays: 30,
                downPayment: '100',
            );

            $this->assertNotNull($plan);
            $this->assertSame(PaymentPlanStatus::Active, $plan->status);
            $this->assertCount(4, $plan->installments);

            $installments = $plan->installments->sortBy('installment_number')->values();

            $this->assertSame('100.00', (string) $installments[0]->amount);
            $this->assertSame(1, $installments[0]->installment_number);

            $totalInstallmentAmount = '0';
            for ($i = 1; $i < 4; $i++) {
                $totalInstallmentAmount = bcadd($totalInstallmentAmount, (string) $installments[$i]->amount, 2);
            }
            $this->assertSame('500.00', $totalInstallmentAmount);
        });
    }

    public function test_cannot_create_plan_on_draft_or_void_invoice(): void
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
                'status' => InvoiceStatus::Draft,
                'invoice_type' => InvoiceType::Standalone,
                'currency' => 'GHS',
            ]);

            $service = app(PaymentPlanService::class);

            $this->expectException(\RuntimeException::class);
            $service->createPlan(invoice: $invoice, installmentCount: 3);
        });
    }

    public function test_cannot_create_duplicate_active_plan(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        Invoice::withoutEvents(function () use ($branch, $patient) {
            $invoice = $this->createIssuedInvoice($branch, $patient, 300);

            $service = app(PaymentPlanService::class);
            $service->createPlan(invoice: $invoice, installmentCount: 2);

            $this->expectException(\RuntimeException::class);
            $service->createPlan(invoice: $invoice, installmentCount: 2);
        });
    }

    public function test_record_installment_payment_updates_installment(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        Invoice::withoutEvents(function () use ($branch, $patient) {
            $invoice = $this->createIssuedInvoice($branch, $patient, 300);

            $service = app(PaymentPlanService::class);
            $plan = $service->createPlan(invoice: $invoice, installmentCount: 3);

            $installment = $plan->installments->sortBy('installment_number')->first();

            $service->recordInstallmentPayment(
                plan: $plan,
                installment: $installment,
                amount: (string) $installment->amount,
                method: PaymentMethod::Cash,
                gateway: 'cash',
            );

            $installment->refresh();
            $this->assertTrue($installment->isFullyPaid());
            $this->assertSame(\Modules\Billing\Enums\PaymentPlanInstallmentStatus::Paid, $installment->status);
            $this->assertNotNull($installment->paid_at);
        });
    }

    public function test_plan_completes_when_all_installments_paid(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        Invoice::withoutEvents(function () use ($branch, $patient) {
            $invoice = $this->createIssuedInvoice($branch, $patient, 200);

            $service = app(PaymentPlanService::class);
            $plan = $service->createPlan(invoice: $invoice, installmentCount: 2);

            $installments = $plan->installments->sortBy('installment_number');

            foreach ($installments as $inst) {
                $service->recordInstallmentPayment(
                    plan: $plan,
                    installment: $inst,
                    amount: (string) $inst->amount,
                    method: PaymentMethod::Cash,
                    gateway: 'cash',
                );
            }

            $plan->refresh();
            $this->assertSame(PaymentPlanStatus::Completed, $plan->status);
        });
    }

    public function test_cancel_plan(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        Invoice::withoutEvents(function () use ($branch, $patient) {
            $invoice = $this->createIssuedInvoice($branch, $patient, 200);

            $service = app(PaymentPlanService::class);
            $plan = $service->createPlan(invoice: $invoice, installmentCount: 2);

            $service->cancelPlan($plan);

            $plan->refresh();
            $this->assertSame(PaymentPlanStatus::Cancelled, $plan->status);
        });
    }

    protected function createIssuedInvoice($branch, $patient, float $total): Invoice
    {
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
            'description' => 'Service fee',
            'quantity' => 1,
            'unit_price' => $total,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => $total,
            'amount_paid' => 0,
            'line_status' => InvoiceLineStatus::Unpaid,
            'patient_responsibility_amount' => $total,
        ]);

        $invoice = $invoice->fresh(['lines']);
        app(InvoiceIssuanceService::class)->issue($invoice);

        return $invoice->fresh();
    }
}
