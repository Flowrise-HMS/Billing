<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PatientDepositStatus;
use Modules\Billing\Filament\Clusters\Billing\Pages\DepositsAndOutstanding;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PatientDeposit;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class DepositsAndOutstandingPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_page_renders(): void
    {
        $user = User::factory()->create();
        Gate::before(fn () => true);

        Livewire::actingAs($user)
            ->test(DepositsAndOutstanding::class)
            ->assertOk();
    }

    public function test_page_renders_with_data(): void
    {
        $user = User::factory()->create();
        Gate::before(fn () => true);

        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create([
                'branch_id' => $branch->id,
                'first_name' => 'Ama',
                'last_name' => 'Mensah',
                'mrn' => 'FR-DEP-AMA-001',
            ])
        );

        PatientDeposit::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'amount' => '150.00',
            'unallocated_balance' => '60.00',
            'status' => PatientDepositStatus::Active,
            'recorded_by' => $user->id,
        ]);

        Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
            'status' => InvoiceStatus::PartiallyPaid,
            'invoice_type' => InvoiceType::Standalone,
            'currency' => 'GHS',
            'total' => 100,
            'amount_paid' => 40,
            'issued_at' => now(),
        ]));

        Livewire::actingAs($user)
            ->test(DepositsAndOutstanding::class)
            ->assertOk();
    }
}
