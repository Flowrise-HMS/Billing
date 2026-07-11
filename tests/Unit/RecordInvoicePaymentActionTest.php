<?php

namespace Modules\Billing\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Filament\Actions\RecordInvoicePaymentAction;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\Payment;
use Tests\TestCase;

class RecordInvoicePaymentActionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_uses_database_from_env_testing(): void
    {
        $this->migrateModules(['Core']);

        $expectedDatabase = (string) ($_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? getenv('DB_DATABASE') ?: '');
        $connection = (string) config('database.default');
        $configuredDatabase = (string) config("database.connections.{$connection}.database");

        $this->assertSame($expectedDatabase, $configuredDatabase);
        $this->assertMatchesRegularExpression('/_test(?:_\d+)?$/', $configuredDatabase);
    }

    public function test_form_fill_state_includes_payable_line_items_and_balance(): void
    {
        $invoice = Invoice::factory()->create();

        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Lab test',
            'quantity' => 1,
            'unit_price' => '45.00',
            'line_status' => InvoiceLineStatus::Unpaid,
            'amount_paid' => '0.00',
        ]);

        $invoice->refresh();

        $state = RecordInvoicePaymentAction::formFillState($invoice, []);

        $this->assertSame((string) $invoice->id, $state['invoice_id']);
        $this->assertSame('full', $state['payment_mode']);
        $this->assertSame($invoice->balanceDue(), $state['amount']);
        $this->assertCount(1, $state['line_items']);
        $this->assertSame('Lab test (qty: 1)', $state['line_items'][0]['description']);
    }

    public function test_resolve_invoice_id_prefers_invoice_record_over_payment_model(): void
    {
        $invoice = new Invoice;
        $invoice->id = 'invoice-uuid';

        $payment = new Payment;
        $payment->id = 'payment-uuid';

        $resolve = new \ReflectionMethod(RecordInvoicePaymentAction::class, 'resolveInvoiceId');
        $resolve->setAccessible(true);

        $this->assertSame('invoice-uuid', $resolve->invoke(null, $invoice, []));
        $this->assertSame('invoice-uuid', $resolve->invoke(null, $invoice, ['invoice_id' => 'other']));
        $this->assertSame('arg-invoice', $resolve->invoke(null, $payment, ['invoice_id' => 'arg-invoice']));
        $this->assertNull($resolve->invoke(null, $payment, []));
    }

    public function test_payable_line_items_for_invoice_excludes_void_and_fully_paid_lines(): void
    {
        $invoice = Invoice::factory()->create();

        $payableLine = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Consultation',
            'quantity' => 2,
            'unit_price' => '50.00',
            'line_status' => InvoiceLineStatus::Unpaid,
            'amount_paid' => '0.00',
        ]);

        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_status' => InvoiceLineStatus::Void,
            'quantity' => 1,
            'unit_price' => '50.00',
            'amount_paid' => '0.00',
        ]);

        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_status' => InvoiceLineStatus::Paid,
            'quantity' => 1,
            'unit_price' => '75.00',
            'amount_paid' => '75.00',
        ]);

        $invoice->load('lines');

        $items = RecordInvoicePaymentAction::payableLineItemsForInvoice($invoice);

        $this->assertCount(1, $items);
        $this->assertSame((string) $payableLine->id, $items[0]['line_id']);
        $this->assertSame('Consultation (qty: 2)', $items[0]['description']);
        $this->assertSame('100.00', $items[0]['amount']);
        $this->assertTrue($items[0]['selected']);
    }

    public function test_payable_line_items_for_invoice_can_filter_to_single_line(): void
    {
        $invoice = Invoice::factory()->create();

        $firstLine = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_status' => InvoiceLineStatus::Unpaid,
            'quantity' => 1,
            'unit_price' => '40.00',
            'amount_paid' => '0.00',
        ]);

        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_status' => InvoiceLineStatus::Unpaid,
            'quantity' => 1,
            'unit_price' => '60.00',
            'amount_paid' => '0.00',
        ]);

        $invoice->load('lines');

        $items = RecordInvoicePaymentAction::payableLineItemsForInvoice($invoice, (string) $firstLine->id);

        $this->assertCount(1, $items);
        $this->assertSame((string) $firstLine->id, $items[0]['line_id']);
    }

    public function test_build_selected_allocations_skips_unselected_lines(): void
    {
        $invoice = Invoice::factory()->create();

        $selectedLine = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_status' => InvoiceLineStatus::Unpaid,
            'quantity' => 1,
            'unit_price' => '30.00',
            'amount_paid' => '0.00',
        ]);

        $skippedLine = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_status' => InvoiceLineStatus::Unpaid,
            'quantity' => 1,
            'unit_price' => '20.00',
            'amount_paid' => '0.00',
        ]);

        $invoice->load('lines');

        $build = new \ReflectionMethod(RecordInvoicePaymentAction::class, 'buildSelectedAllocations');
        $build->setAccessible(true);

        $allocations = $build->invoke(null, [
            [
                'line_id' => (string) $selectedLine->id,
                'amount' => '30.00',
                'selected' => true,
            ],
            [
                'line_id' => (string) $skippedLine->id,
                'amount' => '20.00',
                'selected' => false,
            ],
        ], $invoice);

        $this->assertSame(['30.00'], array_values($allocations));
        $this->assertArrayHasKey((string) $selectedLine->id, $allocations);
        $this->assertArrayNotHasKey((string) $skippedLine->id, $allocations);
    }
}
