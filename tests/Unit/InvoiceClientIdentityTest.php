<?php

namespace Modules\Billing\Tests\Unit;

use Modules\Billing\Models\Invoice;
use Modules\Core\Models\Branch;
use Modules\Core\Support\ClientIdentity;
use Tests\TestCase;

class InvoiceClientIdentityTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_guest_invoice_resolves_client_identity(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);

        $invoice = Invoice::factory()->create([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'guest_name' => 'Walk-in Client',
            'guest_phone' => '+233201234567',
            'guest_email' => 'walkin@example.com',
        ]);

        $identity = $invoice->clientIdentity();

        $this->assertSame(ClientIdentity::TYPE_GUEST, $identity->type);
        $this->assertSame('Walk-in Client', $identity->name);
        $this->assertSame('+233201234567', $identity->phone);
        $this->assertTrue($invoice->isGuest());
    }

    public function test_guest_invoice_pdf_includes_client_name(): void
    {
        $branch = Branch::factory()->create(['is_active' => true]);

        $invoice = Invoice::factory()->create([
            'branch_id' => $branch->id,
            'patient_id' => null,
            'guest_name' => 'PDF Guest Client',
            'guest_phone' => '+233209876543',
        ]);

        $html = view('billing::pdf.invoice', [
            'invoice' => $invoice->loadMissing(['patient', 'branch', 'encounter', 'lines.service']),
        ])->render();

        $this->assertStringContainsString('PDF Guest Client', $html);
        $this->assertStringContainsString('+233209876543', $html);
        $this->assertStringNotContainsString('>N/A<', $html);
    }
}
