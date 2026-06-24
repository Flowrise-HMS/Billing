<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Services\EncounterInvoiceService;
use Modules\Clinical\Database\Factories\EncounterFactory;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class EncounterDraftInvoiceConcurrencyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Billing']);
    }

    public function test_sequential_ensure_draft_returns_same_invoice(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $encounter = EncounterFactory::new()
            ->forPatient($patient)
            ->create();

        $service = app(EncounterInvoiceService::class);

        $first = $service->ensureDraftInvoiceForEncounter($encounter);
        $second = $service->ensureDraftInvoiceForEncounter($encounter->fresh());

        $this->assertSame($first->id, $second->id);
        $this->assertSame(InvoiceStatus::Draft, $second->status);
    }
}
