<?php

namespace Modules\Billing\Tests\Feature;

use Carbon\Carbon;
use Illuminate\Auth\GenericUser;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class RevenueReportCsvTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Appointment', 'Billing']);
    }

    public function test_revenue_csv_includes_daily_trend_section(): void
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

        Payment::query()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'method' => PaymentMethod::Cash,
            'gateway' => 'cash',
            'amount' => 50,
            'currency' => 'GHS',
            'provider_transaction_id' => 'csv-txn',
            'received_at' => '2026-05-15 10:00:00',
        ]);

        $user = new GenericUser([
            'id' => 1,
            'name' => 'Billing CSV Test',
            'email' => 'csv.test@example.com',
        ]);

        $response = $this->actingAs($user)->get(route('billing.reports.revenue.csv', [
            'preset' => 'today',
        ]));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $content = (string) $response->streamedContent();
        $this->assertStringContainsString('Daily Trend', $content);
        $this->assertStringContainsString('2026-05-15', $content);
        $this->assertStringContainsString('Recent Payments', $content);
    }
}
