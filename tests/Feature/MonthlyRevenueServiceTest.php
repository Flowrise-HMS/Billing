<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\MonthlyRevenueService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Models\Branch;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class MonthlyRevenueServiceTest extends TestCase
{
    use DatabaseTransactions;

    private Branch $branch;
    private User $cashier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
        $this->branch = BranchFactory::new()->create();
        $this->cashier = User::factory()->create();
    }

    public function test_monthly_revenue_uses_shared_predicate_and_groups_by_method(): void
    {
        $date = CarbonImmutable::parse('2026-08-15');
        $make = fn (PaymentType $t, PaymentMethod $m, string $amount, string $day) => Payment::create([
            'patient_id' => Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $this->branch->id])->id),
            'branch_id' => $this->branch->id,
            'method' => $m,
            'gateway' => 'cash',
            'type' => $t,
            'amount' => $amount,
            'currency' => 'GHS',
            'provider_transaction_id' => 'txn-'.Str::uuid(),
            'received_at' => $date->copy()->subDays((int) $day),
            'recorded_by' => $this->cashier->id,
            'metadata' => [],
        ]);

        $make(PaymentType::Payment, PaymentMethod::Cash, '100.00', '0');
        $make(PaymentType::Payment, PaymentMethod::MobileMoney, '50.00', '1');
        $make(PaymentType::Deposit, PaymentMethod::Cash, '900.00', '2');
        $make(PaymentType::Refund, PaymentMethod::Gateway, '-20.00', '3');

        $out = app(MonthlyRevenueService::class)->monthly($this->branch, $date);

        $this->assertSame('150.00', $out['revenue_total']);
        $this->assertSame('20.00', $out['refunds_total']);
        $this->assertSame('130.00', $out['net_revenue']);
        $this->assertSame('100.00', $out['revenue_by_method']['cash']);
        $this->assertSame('50.00', $out['revenue_by_method']['mobile_money']);
    }

    public function test_monthly_revenue_gates_insurance_split_on_module_availability(): void
    {
        $date = CarbonImmutable::parse('2026-08-15');

        $out = app(MonthlyRevenueService::class)->monthly($this->branch, $date);

        if (\Modules\Core\Support\ModuleAvailability::insuranceEnabled()) {
            $this->assertArrayHasKey('insurance_split', $out);
        } else {
            $this->assertArrayNotHasKey('insurance_split', $out);
        }
    }

    public function test_monthly_revenue_groups_by_service_category(): void
    {
        $date = CarbonImmutable::parse('2026-08-15');
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $this->branch->id]));
        $invoice = Invoice::withoutEvents(fn () => \Modules\Billing\Database\Factories\InvoiceFactory::new()->create([
            'organization_id' => $this->branch->organization_id,
            'branch_id' => $this->branch->id,
            'patient_id' => $patient->id,
            'invoice_type' => InvoiceType::Standalone,
            'status' => InvoiceStatus::Issued,
            'currency' => 'GHS',
            'issued_at' => $date,
        ]));

        $line = \Modules\Billing\Database\Factories\InvoiceLineFactory::new()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => '100.00',
            'line_total' => '100.00',
        ]);

        $payment = Payment::create([
            'patient_id' => $patient->id,
            'branch_id' => $this->branch->id,
            'method' => PaymentMethod::Cash,
            'gateway' => 'cash',
            'type' => PaymentType::Payment,
            'amount' => '100.00',
            'currency' => 'GHS',
            'provider_transaction_id' => 'txn-'.Str::uuid(),
            'received_at' => $date,
            'recorded_by' => $this->cashier->id,
            'metadata' => [],
        ]);
        $payment->allocations()->create(['invoice_line_id' => $line->id, 'amount' => '100.00']);

        $out = app(MonthlyRevenueService::class)->monthly($this->branch, $date);

        $this->assertArrayHasKey((string) $line->service_id, $out['revenue_by_category']);
        $this->assertSame('100.00', $out['revenue_by_category'][(string) $line->service_id]);
    }
}
