<?php

namespace Modules\Billing\Tests\Feature;

use Carbon\Carbon;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\RevenueReportService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class RevenueReportServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Patient', 'Clinical', 'Appointment', 'Billing'] as $module) {
            $this->artisan('module:migrate', ['module' => $module, '--force' => true]);
        }
    }

    public function test_it_builds_summary_breakdowns_and_aging(): void
    {
        Carbon::setTestNow('2026-05-31 12:00:00');

        $branchA = BranchFactory::new()->create(['name' => 'Main']);
        $branchB = BranchFactory::new()->create(['name' => 'Annex']);

        $patientA = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branchA->id]));
        $patientB = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branchB->id]));

        $invoiceA = Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $branchA->organization_id,
            'branch_id' => $branchA->id,
            'patient_id' => $patientA->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'status' => InvoiceStatus::PartiallyPaid,
            'invoice_type' => InvoiceType::Standalone,
            'currency' => 'GHS',
            'issued_at' => '2026-05-08 08:00:00',
            'due_at' => '2026-05-10 00:00:00',
            'total' => 100,
            'amount_paid' => 40,
        ]));

        Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $branchA->organization_id,
            'branch_id' => $branchA->id,
            'patient_id' => $patientA->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'status' => InvoiceStatus::Paid,
            'invoice_type' => InvoiceType::Standalone,
            'currency' => 'GHS',
            'issued_at' => '2026-05-15 08:00:00',
            'due_at' => '2026-05-20 00:00:00',
            'total' => 200,
            'amount_paid' => 200,
        ]));

        $invoiceB = Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $branchB->organization_id,
            'branch_id' => $branchB->id,
            'patient_id' => $patientB->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'status' => InvoiceStatus::Issued,
            'invoice_type' => InvoiceType::Standalone,
            'currency' => 'GHS',
            'issued_at' => '2026-05-05 08:00:00',
            'due_at' => '2026-03-01 00:00:00',
            'total' => 80,
            'amount_paid' => 0,
        ]));

        Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $branchA->organization_id,
            'branch_id' => $branchA->id,
            'patient_id' => $patientA->id,
            'invoice_number' => Invoice::generateInvoiceNumber(),
            'status' => InvoiceStatus::Draft,
            'invoice_type' => InvoiceType::Standalone,
            'currency' => 'GHS',
            'issued_at' => '2026-05-10 08:00:00',
            'total' => 50,
            'amount_paid' => 0,
        ]));

        Payment::query()->create([
            'patient_id' => $patientA->id,
            'branch_id' => $branchA->id,
            'method' => PaymentMethod::Cash,
            'gateway' => 'cash',
            'amount' => 40,
            'currency' => 'GHS',
            'provider_transaction_id' => 'txn-a',
            'received_at' => '2026-05-12 10:00:00',
        ]);

        Payment::query()->create([
            'patient_id' => $patientA->id,
            'branch_id' => $branchA->id,
            'method' => PaymentMethod::Card,
            'gateway' => 'card',
            'amount' => 200,
            'currency' => 'GHS',
            'provider_transaction_id' => 'txn-b',
            'received_at' => '2026-05-21 10:00:00',
        ]);

        Payment::query()->create([
            'patient_id' => $patientB->id,
            'branch_id' => $branchB->id,
            'method' => PaymentMethod::Cash,
            'gateway' => 'cash',
            'amount' => 10,
            'currency' => 'GHS',
            'provider_transaction_id' => 'txn-c',
            'received_at' => '2026-04-21 10:00:00',
        ]);

        $report = app(RevenueReportService::class)->build(
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31')
        );

        $this->assertSame('380', (string) $report['summary']['billed']);
        $this->assertSame('240', (string) $report['summary']['collected']);
        $this->assertSame('140.00', (string) $report['summary']['outstanding']);

        $this->assertCount(1, $report['branch_breakdown']);
        $this->assertSame('Main', $report['branch_breakdown'][0]['branch_name']);
        $this->assertSame('240', (string) $report['branch_breakdown'][0]['total_collected']);

        $this->assertCount(2, $report['method_breakdown']);
        $methodMap = collect($report['method_breakdown'])->keyBy('method');
        $this->assertSame('40', (string) $methodMap['cash']['total_collected']);
        $this->assertSame('200', (string) $methodMap['card']['total_collected']);

        $aging = collect($report['aging'])->keyBy('bucket');
        $this->assertSame('60.00', (string) $aging['1-30 days']['amount']);
        $this->assertSame(1, (int) $aging['1-30 days']['count']);
        $this->assertSame('80.00', (string) $aging['90+ days']['amount']);
        $this->assertSame(1, (int) $aging['90+ days']['count']);

        $branchOnly = app(RevenueReportService::class)->build(
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31'),
            (string) $branchA->id
        );

        $this->assertSame('300', (string) $branchOnly['summary']['billed']);
        $this->assertSame('240', (string) $branchOnly['summary']['collected']);
        $this->assertSame('60.00', (string) $branchOnly['summary']['outstanding']);

        // keep references used to avoid accidental cleanup by formatters
        $this->assertNotNull($invoiceA->id);
        $this->assertNotNull($invoiceB->id);
    }
}
