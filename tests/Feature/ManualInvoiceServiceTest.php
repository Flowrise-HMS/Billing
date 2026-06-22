<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\ManualInvoiceService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class ManualInvoiceServiceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Appointment', 'Billing']);
    }

    public function test_creates_standalone_draft_invoice(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $invoice = Invoice::withoutEvents(
            fn () => app(ManualInvoiceService::class)->createStandaloneDraft(
                branchId: (string) $branch->id,
                patientId: (string) $patient->id,
                currency: 'GHS',
                invoiceType: InvoiceType::Standalone,
            )
        );

        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame(InvoiceType::Standalone, $invoice->invoice_type);
        $this->assertSame((string) $patient->id, (string) $invoice->patient_id);
    }
}
