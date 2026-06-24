<?php

namespace Modules\Billing\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Models\BillingWebhookEvent;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\PatientDeposit;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PaymentAllocation;
use Modules\Billing\Models\PaymentIntent;
use Tests\TestCase;

class BillingFactorySmokeTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_invoice_factory(): void
    {
        $invoice = Invoice::factory()->create();
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertTrue($invoice->exists);
        $this->assertNotNull($invoice->id);
        $this->assertNotNull($invoice->invoice_number);
    }

    public function test_invoice_line_factory(): void
    {
        $line = InvoiceLine::factory()->create();
        $this->assertInstanceOf(InvoiceLine::class, $line);
        $this->assertTrue($line->exists);
    }

    public function test_payment_factory(): void
    {
        $payment = Payment::factory()->create();
        $this->assertInstanceOf(Payment::class, $payment);
        $this->assertTrue($payment->exists);
    }

    public function test_patient_deposit_factory(): void
    {
        $deposit = PatientDeposit::factory()->create();
        $this->assertInstanceOf(PatientDeposit::class, $deposit);
        $this->assertTrue($deposit->exists);
    }

    public function test_payment_allocation_factory(): void
    {
        $allocation = PaymentAllocation::factory()->create();
        $this->assertInstanceOf(PaymentAllocation::class, $allocation);
        $this->assertTrue($allocation->exists);
    }

    public function test_payment_intent_factory(): void
    {
        $intent = PaymentIntent::factory()->create();
        $this->assertInstanceOf(PaymentIntent::class, $intent);
        $this->assertTrue($intent->exists);
    }

    public function test_branch_payment_gateway_config_factory(): void
    {
        $config = BranchPaymentGatewayConfig::factory()->create();
        $this->assertInstanceOf(BranchPaymentGatewayConfig::class, $config);
        $this->assertTrue($config->exists);
    }

    public function test_billing_webhook_event_factory(): void
    {
        $event = BillingWebhookEvent::factory()->create();
        $this->assertInstanceOf(BillingWebhookEvent::class, $event);
        $this->assertTrue($event->exists);
    }
}
