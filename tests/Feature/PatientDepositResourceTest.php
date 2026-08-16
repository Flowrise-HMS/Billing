<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Filament\Clusters\Billing\Resources\PatientDeposits\Pages\ListPatientDeposits;
use Modules\Billing\Services\DepositRecordingService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class PatientDepositResourceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_list_page_renders_and_shows_deposits(): void
    {
        $user = User::factory()->create();
        Gate::before(fn () => true);
        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));

        app(DepositRecordingService::class)->record(
            patientId: (string) $patient->id,
            branchId: (string) $branch->id,
            amount: '50.00',
            method: PaymentMethod::Cash,
            recordedBy: $user->id,
        );

        Livewire::actingAs($user)
            ->test(ListPatientDeposits::class)
            ->assertOk()
            ->assertSee('50.00');
    }
}
