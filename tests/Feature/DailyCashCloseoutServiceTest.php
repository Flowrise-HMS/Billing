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
use Modules\Billing\Services\DailyCashCloseoutService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Models\Branch;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class DailyCashCloseoutServiceTest extends TestCase
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

    private function payment(
        PaymentType $type,
        PaymentMethod $method,
        string $amount,
        array $metadata = [],
        ?string $gateway = 'cash',
        ?User $cashier = null,
        ?string $currency = 'GHS',
        ?CarbonImmutable $createdAt = null,
    ): Payment {
        $payment = Payment::create([
            'patient_id' => Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $this->branch->id])->id),
            'branch_id' => $this->branch->id,
            'method' => $method,
            'gateway' => $gateway,
            'type' => $type,
            'amount' => $amount,
            'currency' => $currency,
            'provider_transaction_id' => 'txn-'.Str::uuid(),
            'received_at' => now(),
            'recorded_by' => ($cashier ?? $this->cashier)->id,
            'metadata' => $metadata,
        ]);

        $payment->forceFill(['created_at' => $createdAt ?? now()])->save();

        return $payment;
    }

    public function test_breaks_revenue_down_by_method_and_net_of_refunds(): void
    {
        $this->payment(PaymentType::Payment, PaymentMethod::Cash, '100.00');
        $this->payment(PaymentType::Payment, PaymentMethod::MobileMoney, '50.00');
        $this->payment(PaymentType::Deposit, PaymentMethod::Cash, '20.00');
        $this->payment(PaymentType::WriteOff, PaymentMethod::Cash, '5.00');
        $this->payment(PaymentType::Refund, PaymentMethod::Gateway, '-10.00', [], 'refund');

        $out = app(DailyCashCloseoutService::class)->compute($this->branch, $this->cashier, CarbonImmutable::today());

        $this->assertSame('100.00', $out['revenue_by_method']['cash']);
        $this->assertSame('50.00', $out['revenue_by_method']['mobile_money']);
        $this->assertArrayNotHasKey('gateway', $out['revenue_by_method']);
        $this->assertSame('-10.00', $out['refunds_total']);
        $this->assertSame('140.00', $out['net_revenue']);
        $this->assertSame('20.00', $out['deposits_received']);
        $this->assertSame('120.00', $out['cash_in']);
    }

    public function test_aggregates_cash_refunds_and_change_given(): void
    {
        $cashPayment = $this->payment(PaymentType::Payment, PaymentMethod::Cash, '100.00', ['change_due' => '5.00']);
        $this->payment(PaymentType::Payment, PaymentMethod::Cash, '40.00', ['change_due' => '0.00']);
        $this->payment(
            PaymentType::Refund,
            PaymentMethod::Gateway,
            '-15.00',
            ['original_payment_id' => $cashPayment->id, 'action' => 'refund'],
            'refund',
        );

        $out = app(DailyCashCloseoutService::class)->compute($this->branch, $this->cashier, CarbonImmutable::today());

        $this->assertSame('15.00', $out['cash_refunds']);
        $this->assertSame('5.00', $out['change_given']);
    }

    public function test_scopes_to_cashier_and_ignores_other_currencies_in_totals(): void
    {
        $otherCashier = User::factory()->create();
        $this->payment(PaymentType::Payment, PaymentMethod::Cash, '500.00', [], 'cash', $otherCashier);
        $this->payment(PaymentType::Payment, PaymentMethod::Cash, '30.00', [], 'cash', $this->cashier);
        $this->payment(PaymentType::Payment, PaymentMethod::Cash, '10.00', [], 'cash', $this->cashier, 'USD');

        $out = app(DailyCashCloseoutService::class)->compute($this->branch, $this->cashier, CarbonImmutable::today());

        $this->assertSame('30.00', $out['revenue_by_method']['cash']);
        $this->assertSame('USD', $out['non_ghs_currencies'][0]);
    }

    public function test_tax_collected_is_prorated_to_allocated_share(): void
    {
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $this->branch->id]));

        $invoice = Invoice::withoutEvents(fn () => \Modules\Billing\Database\Factories\InvoiceFactory::new()->create([
            'organization_id' => $this->branch->organization_id,
            'branch_id' => $this->branch->id,
            'patient_id' => $patient->id,
            'invoice_type' => InvoiceType::Standalone,
            'status' => InvoiceStatus::Issued,
            'currency' => 'GHS',
            'issued_at' => now(),
        ]));

        $line = \Modules\Billing\Database\Factories\InvoiceLineFactory::new()->create([
            'invoice_id' => $invoice->id,
            'quantity' => 1,
            'unit_price' => '100.00',
            'tax_amount' => '20.00',
            'line_total' => '120.00',
        ]);

        $payment = $this->payment(PaymentType::Payment, PaymentMethod::Cash, '120.00');
        $payment->allocations()->create(['invoice_line_id' => $line->id, 'amount' => '120.00']);

        $out = app(DailyCashCloseoutService::class)->compute($this->branch, $this->cashier, CarbonImmutable::today());

        $this->assertSame('24.00', $out['tax_collected']);
    }

    public function test_post_finalize_cash_affecting_rows_are_flagged(): void
    {
        $finalizedAt = CarbonImmutable::now()->subMinute();
        $this->payment(PaymentType::Payment, PaymentMethod::Cash, '50.00', [], 'cash', null, 'GHS', $finalizedAt->copy()->subMinutes(5));
        $this->payment(PaymentType::Payment, PaymentMethod::Cash, '25.00');

        $stale = app(DailyCashCloseoutService::class)->postFinalizeTransactions(
            $this->branch, $this->cashier, CarbonImmutable::today(), $finalizedAt,
        );

        $this->assertCount(1, $stale);
        $this->assertSame('25.00', $stale->first()->amount);
    }
}
