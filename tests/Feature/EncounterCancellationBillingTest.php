<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\EncounterInvoiceService;
use Modules\Clinical\Database\Factories\EncounterFactory;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Events\EncounterCancelled;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class EncounterCancellationBillingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Billing']);
    }

    public function test_encounter_cancelled_voids_draft_invoice(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $encounter = EncounterFactory::new()
            ->forPatient($patient)
            ->create(['status' => EncounterStatus::IN_PROGRESS]);

        $invoice = app(EncounterInvoiceService::class)->ensureDraftInvoiceForEncounter($encounter);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);

        EncounterCancelled::dispatch($encounter->fresh());

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Void, $invoice->status);

        $this->assertSame(1, Invoice::query()->withoutGlobalScopes()
            ->where('encounter_id', $encounter->id)
            ->count());
    }
}
