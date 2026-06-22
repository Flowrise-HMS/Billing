<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PaymentIntent;
use Modules\Core\Models\Branch;
use Tests\TestCase;

class EdgeCaseTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    // ─── InvoiceStatus enum ──────────────────────────────────────────────────

    public function test_invoice_status_values(): void
    {
        $values = InvoiceStatus::values();
        $this->assertContains('draft', $values);
        $this->assertContains('issued', $values);
        $this->assertContains('partially_paid', $values);
        $this->assertContains('paid', $values);
        $this->assertContains('void', $values);
        $this->assertCount(5, $values);
    }

    public function test_invoice_status_labels_and_colors(): void
    {
        $this->assertSame('Draft', InvoiceStatus::Draft->getLabel());
        $this->assertSame('Paid', InvoiceStatus::Paid->getLabel());
        $this->assertSame('Void', InvoiceStatus::Void->getLabel());
        $this->assertSame('success', InvoiceStatus::Paid->getColor());
        $this->assertSame('danger', InvoiceStatus::Void->getColor());
    }

    // ─── InvoiceType enum ────────────────────────────────────────────────────

    public function test_invoice_type_values(): void
    {
        $values = InvoiceType::values();
        $this->assertContains('standalone', $values);
        $this->assertContains('interim', $values);
        $this->assertContains('final', $values);
        $this->assertCount(3, $values);
    }

    // ─── InvoiceLineStatus enum ──────────────────────────────────────────────

    public function test_invoice_line_status_values(): void
    {
        $values = InvoiceLineStatus::values();
        $this->assertContains('unpaid', $values);
        $this->assertContains('partial', $values);
        $this->assertContains('paid', $values);
        $this->assertContains('void', $values);
        $this->assertCount(4, $values);
    }

    // ─── PaymentIntentStatus enum ────────────────────────────────────────────

    public function test_payment_intent_status_values(): void
    {
        $values = PaymentIntentStatus::values();
        $this->assertContains('pending', $values);
        $this->assertContains('succeeded', $values);
        $this->assertContains('failed', $values);
        $this->assertContains('expired', $values);
        $this->assertContains('cancelled', $values);
        $this->assertCount(5, $values);
    }

    // ─── PaymentMethod enum ──────────────────────────────────────────────────

    public function test_payment_method_enum(): void
    {
        $values = PaymentMethod::values();
        $this->assertContains('cash', $values);
        $this->assertContains('card', $values);
        $this->assertContains('bank_transfer', $values);
        $this->assertContains('mobile_money', $values);
        $this->assertContains('gateway', $values);
    }

    // ─── Invoice model ───────────────────────────────────────────────────────

    public function test_invoice_has_uuid(): void
    {
        $invoice = Invoice::factory()->create();
        $this->assertNotNull($invoice->id);
        $this->assertTrue(strlen((string) $invoice->id) === 36);
    }

    public function test_invoice_casts_status_as_enum(): void
    {
        $invoice = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);
        $this->assertTrue($invoice->status instanceof InvoiceStatus);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
    }

    public function test_invoice_is_draft(): void
    {
        $draft = Invoice::factory()->create(['status' => InvoiceStatus::Draft]);
        $this->assertTrue($draft->isDraft());
        $issued = Invoice::factory()->create(['status' => InvoiceStatus::Issued]);
        $this->assertFalse($issued->isDraft());
    }

    public function test_invoice_balance_due(): void
    {
        $invoice = Invoice::factory()->create([
            'total' => 100.00,
            'amount_paid' => 30.00,
        ]);
        $this->assertSame('70.00', $invoice->balanceDue());
    }

    public function test_invoice_balance_due_full(): void
    {
        $invoice = Invoice::factory()->create([
            'total' => 100.00,
            'amount_paid' => 100.00,
        ]);
        $this->assertSame('0.00', $invoice->balanceDue());
    }

    public function test_invoice_zero_total(): void
    {
        $invoice = Invoice::factory()->create([
            'total' => 0.00,
            'amount_paid' => 0.00,
        ]);
        $this->assertSame('0.00', $invoice->balanceDue());
    }

    // ─── InvoiceLine model ───────────────────────────────────────────────────

    public function test_invoice_line_belongs_to_invoice(): void
    {
        $line = InvoiceLine::factory()->create();
        $this->assertNotNull($line->invoice);
    }

    public function test_invoice_line_auto_calculates_line_total(): void
    {
        $line = InvoiceLine::factory()->create([
            'unit_price' => 50.00,
            'quantity' => 3,
            'discount_amount' => 10.00,
            'tax_amount' => 5.00,
            'line_total' => null,
        ]);
        $this->assertNotNull($line->line_total);
        $this->assertSame('145.00', number_format((float) $line->line_total, 2));
    }

    public function test_invoice_line_forces_minimum_quantity(): void
    {
        $line = InvoiceLine::factory()->create(['quantity' => 0]);
        $this->assertGreaterThanOrEqual(1, $line->quantity);
    }

    // ─── Payment model ───────────────────────────────────────────────────────

    public function test_payment_has_uuid(): void
    {
        $payment = Payment::factory()->create();
        $this->assertNotNull($payment->id);
        $this->assertTrue(strlen((string) $payment->id) === 36);
    }

    public function test_payment_casts_method_as_enum(): void
    {
        $payment = Payment::factory()->create(['method' => PaymentMethod::Cash]);
        $this->assertTrue($payment->method instanceof PaymentMethod);
        $this->assertSame(PaymentMethod::Cash, $payment->method);
    }

    // ─── PaymentIntent model ─────────────────────────────────────────────────

    public function test_payment_intent_casts_status_as_enum(): void
    {
        $intent = PaymentIntent::factory()->create(['status' => PaymentIntentStatus::Pending]);
        $this->assertTrue($intent->status instanceof PaymentIntentStatus);
        $this->assertSame(PaymentIntentStatus::Pending, $intent->status);
    }

    // ─── Invoice auto-numbering ──────────────────────────────────────────────

    public function test_generate_invoice_number_format(): void
    {
        $branch = Branch::factory()->create();
        $number = Invoice::generateInvoiceNumber($branch->id);
        $this->assertStringStartsWith('INV-', $number);
        $this->assertStringContainsString(now()->format('Ymd'), $number);
    }

    public function test_generate_invoice_number_uniqueness(): void
    {
        $branch = Branch::factory()->create();
        $num1 = Invoice::generateInvoiceNumber($branch->id);
        $num2 = Invoice::generateInvoiceNumber($branch->id);
        $this->assertNotSame($num1, $num2);
    }
}
