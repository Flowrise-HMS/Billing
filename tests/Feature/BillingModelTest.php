<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\Payment;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class BillingModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('module:migrate', ['module' => 'Core', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Patient', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Billing', '--force' => true]);
    }

    public function test_invoice_belongs_to_branch(): void
    {
        $branch = Branch::factory()->create();
        $invoice = Invoice::factory()->create(['branch_id' => $branch->id]);

        $this->assertEquals($branch->id, $invoice->branch->id);
    }

    public function test_invoice_belongs_to_patient(): void
    {
        $patient = Patient::factory()->create();
        $invoice = Invoice::factory()->create(['patient_id' => $patient->id]);

        $this->assertEquals($patient->id, $invoice->patient->id);
    }

    public function test_invoice_has_lines(): void
    {
        $invoice = Invoice::factory()->create();
        InvoiceLine::factory()->count(3)->create(['invoice_id' => $invoice->id]);

        $this->assertCount(3, $invoice->lines);
    }

    public function test_invoice_generates_number(): void
    {
        $branch = Branch::factory()->create();
        $number = Invoice::generateInvoiceNumber($branch->id);

        $this->assertNotNull($number);
        $this->assertStringStartsWith('INV-', $number);
    }
}
