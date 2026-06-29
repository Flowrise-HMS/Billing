<?php

namespace Modules\Billing\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PaymentAllocation;
use Modules\Core\Models\Branch;
use Modules\Core\Support\ClientIdentity;
use Tests\TestCase;

class PaymentClientIdentityTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_payment_without_patient_resolves_client_from_guest_invoice(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);

        $invoice = Invoice::factory()->create([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'guest_name' => 'Receipt Guest',
            'guest_phone' => '+233201111111',
        ]);

        $line = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_status' => InvoiceLineStatus::Unpaid,
        ]);

        $payment = Payment::factory()->create([
            'patient_id' => null,
            'branch_id' => $branch->id,
            'method' => PaymentMethod::Cash,
            'gateway' => 'cash',
        ]);

        PaymentAllocation::factory()->create([
            'payment_id' => $payment->id,
            'invoice_line_id' => $line->id,
            'amount' => '10.00',
        ]);

        $payment->load('allocations.invoiceLine.invoice');

        $identity = $payment->clientIdentity();

        $this->assertSame(ClientIdentity::TYPE_GUEST, $identity->type);
        $this->assertSame('Receipt Guest', $identity->name);
    }

    public function test_guest_payment_receipt_pdf_includes_client_name(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);

        $invoice = Invoice::factory()->create([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'guest_name' => 'Receipt PDF Guest',
            'guest_phone' => '+233202222222',
        ]);

        $line = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_status' => InvoiceLineStatus::Unpaid,
        ]);

        $payment = Payment::factory()->create([
            'patient_id' => null,
            'branch_id' => $branch->id,
            'method' => PaymentMethod::Cash,
            'gateway' => 'cash',
        ]);

        PaymentAllocation::factory()->create([
            'payment_id' => $payment->id,
            'invoice_line_id' => $line->id,
            'amount' => '10.00',
        ]);

        $payment->load('allocations.invoiceLine.invoice', 'branch');

        $html = view('billing::pdf.receipt', [
            'payment' => $payment,
            'client' => $payment->clientIdentity(),
            'allocations' => $payment->allocations,
        ])->render();

        $this->assertStringContainsString('Receipt PDF Guest', $html);
        $this->assertStringNotContainsString('>N/A<', $html);
    }
}
