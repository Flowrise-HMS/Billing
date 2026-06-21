<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Events\UnpaidBillingNoticeRequired;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Services\EncounterInvoiceService;
use Modules\Billing\Services\InvoiceTotalsService;
use Modules\Clinical\Database\Factories\EncounterFactory;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Events\EncounterFinished;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class EncounterDischargeBillingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Billing']);
    }

    public function test_discharge_creates_draft_invoice_with_zero_total_and_does_not_issue(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $encounter = EncounterFactory::new()
            ->forPatient($patient)
            ->create(['status' => EncounterStatus::FINISHED]);

        Event::fake([UnpaidBillingNoticeRequired::class]);

        EncounterFinished::dispatch($encounter->fresh());

        $invoice = Invoice::query()->withoutGlobalScopes()
            ->where('encounter_id', $encounter->id)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertSame('0.00', (string) $invoice->total);

        Event::assertNotDispatched(UnpaidBillingNoticeRequired::class);
    }

    public function test_discharge_issues_invoice_when_total_is_positive(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $encounter = EncounterFactory::new()
            ->forPatient($patient)
            ->active()
            ->create();

        $invoice = app(EncounterInvoiceService::class)->ensureDraftInvoiceForEncounter($encounter);

        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'billable_type' => null,
            'billable_id' => null,
            'description' => 'Service',
            'quantity' => 1,
            'unit_price' => 50,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => 50,
            'amount_paid' => 0,
            'line_status' => InvoiceLineStatus::Unpaid,
            'patient_responsibility_amount' => 50,
        ]);

        app(InvoiceTotalsService::class)->recalculate($invoice->fresh(['lines']));

        Event::fake([UnpaidBillingNoticeRequired::class]);

        EncounterFinished::dispatch($encounter->fresh());

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertNotNull($invoice->issued_at);

        Event::assertDispatched(UnpaidBillingNoticeRequired::class);
    }
}
