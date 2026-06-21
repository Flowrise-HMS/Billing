<?php

namespace Modules\Billing\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Data\BillingReportCriteria;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\RevenueReportService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class RevenueReportServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Appointment', 'Billing']);
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
            'invoice_number' => Invoice::generateInvoiceNumber((string) $branchA->id),
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
            'invoice_number' => Invoice::generateInvoiceNumber((string) $branchA->id),
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
            'invoice_number' => Invoice::generateInvoiceNumber((string) $branchB->id),
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
            'invoice_number' => Invoice::generateInvoiceNumber((string) $branchA->id),
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
        $this->assertSame('63.15', (string) $report['summary']['collection_rate']);

        $branchMap = collect($report['branch_breakdown'])->keyBy('branch_name');
        $this->assertSame('300', (string) $branchMap['Main']['billed']);
        $this->assertSame('240', (string) $branchMap['Main']['total_collected']);
        $this->assertSame('60.00', (string) $branchMap['Main']['outstanding']);
        $this->assertSame('80', (string) $branchMap['Annex']['billed']);
        $this->assertSame('0', (string) $branchMap['Annex']['total_collected']);

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

        $this->assertNotNull($invoiceA->id);
        $this->assertNotNull($invoiceB->id);
    }

    public function test_daily_trend_includes_each_day_in_range(): void
    {
        Carbon::setTestNow('2026-05-03 12:00:00');

        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));

        Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
            'status' => InvoiceStatus::Issued,
            'invoice_type' => InvoiceType::Standalone,
            'currency' => 'GHS',
            'issued_at' => '2026-05-02 10:00:00',
            'total' => 50,
            'amount_paid' => 0,
        ]));

        Payment::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'method' => PaymentMethod::Cash,
            'gateway' => 'cash',
            'amount' => 25,
            'currency' => 'GHS',
            'provider_transaction_id' => 'txn-daily',
            'received_at' => '2026-05-03 14:00:00',
        ]);

        $report = app(RevenueReportService::class)->build(
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-03')
        );

        $this->assertSame(['2026-05-01', '2026-05-02', '2026-05-03'], $report['daily_trend']['labels']);
        $this->assertSame('0', (string) $report['daily_trend']['billed'][0]);
        $this->assertSame('50', (string) $report['daily_trend']['billed'][1]);
        $this->assertSame('0', (string) $report['daily_trend']['billed'][2]);
        $this->assertSame('25', (string) $report['daily_trend']['collected'][2]);
    }

    public function test_recent_payments_lists_payments_in_range(): void
    {
        Carbon::setTestNow('2026-05-10 12:00:00');

        $branch = BranchFactory::new()->create(['name' => 'Central']);
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));

        Payment::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'method' => PaymentMethod::Cash,
            'gateway' => 'cash',
            'amount' => 75,
            'currency' => 'GHS',
            'provider_transaction_id' => 'txn-recent',
            'received_at' => '2026-05-09 09:00:00',
        ]);

        $report = app(RevenueReportService::class)->build(
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31')
        );

        $this->assertCount(1, $report['recent_payments']);
        $this->assertSame('75', (string) $report['recent_payments'][0]['amount']);
        $this->assertSame('Central', $report['recent_payments'][0]['branch_name']);
    }

    public function test_top_outstanding_orders_by_balance_desc(): void
    {
        Carbon::setTestNow('2026-05-31 12:00:00');

        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));

        Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'invoice_number' => 'INV-LOW',
            'status' => InvoiceStatus::Issued,
            'invoice_type' => InvoiceType::Standalone,
            'currency' => 'GHS',
            'issued_at' => '2026-05-01 08:00:00',
            'due_at' => '2026-04-01 00:00:00',
            'total' => 50,
            'amount_paid' => 0,
        ]));

        Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'invoice_number' => 'INV-HIGH',
            'status' => InvoiceStatus::PartiallyPaid,
            'invoice_type' => InvoiceType::Standalone,
            'currency' => 'GHS',
            'issued_at' => '2026-05-01 08:00:00',
            'due_at' => '2026-04-01 00:00:00',
            'total' => 200,
            'amount_paid' => 50,
        ]));

        $report = app(RevenueReportService::class)->build(
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31')
        );

        $this->assertSame('INV-HIGH', $report['top_outstanding'][0]['invoice_number']);
        $this->assertSame('150.00', (string) $report['top_outstanding'][0]['balance']);
        $this->assertSame('INV-LOW', $report['top_outstanding'][1]['invoice_number']);
    }

    public function test_collection_rate_is_null_when_nothing_billed(): void
    {
        Carbon::setTestNow('2026-05-31 12:00:00');

        $report = app(RevenueReportService::class)->build(
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31')
        );

        $this->assertNull($report['summary']['collection_rate']);
    }

    public function test_preset_today_limits_to_single_day(): void
    {
        Carbon::setTestNow('2026-05-15 12:00:00');

        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));

        Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
            'status' => InvoiceStatus::Issued,
            'invoice_type' => InvoiceType::Standalone,
            'currency' => 'GHS',
            'issued_at' => '2026-05-15 08:00:00',
            'total' => 100,
            'amount_paid' => 0,
        ]));

        Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
            'status' => InvoiceStatus::Issued,
            'invoice_type' => InvoiceType::Standalone,
            'currency' => 'GHS',
            'issued_at' => '2026-05-14 08:00:00',
            'total' => 50,
            'amount_paid' => 0,
        ]));

        $criteria = BillingReportCriteria::fromRequest(['preset' => 'today']);
        $report = app(RevenueReportService::class)->buildFromCriteria($criteria);

        $this->assertSame('100', (string) $report['summary']['billed']);
        $this->assertCount(1, $report['daily_trend']['labels']);
        $this->assertSame('2026-05-15', $report['daily_trend']['labels'][0]);
    }

    public function test_insurance_split_when_module_enabled(): void
    {
        Carbon::setTestNow('2026-05-31 12:00:00');
        config()->set('insurance.enabled', true);

        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));

        $invoice = Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
            'status' => InvoiceStatus::Issued,
            'invoice_type' => InvoiceType::Standalone,
            'currency' => 'GHS',
            'issued_at' => '2026-05-10 08:00:00',
            'total' => 100,
            'amount_paid' => 0,
        ]));

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
            'patient_responsibility_amount' => 30,
            'insurance_expected_amount' => 70,
        ]);

        $report = app(RevenueReportService::class)->build(
            Carbon::parse('2026-05-01'),
            Carbon::parse('2026-05-31')
        );

        $this->assertSame('30', (string) $report['insurance_split']['patient_amount']);
        $this->assertSame('70', (string) $report['insurance_split']['insurer_amount']);
    }
}
