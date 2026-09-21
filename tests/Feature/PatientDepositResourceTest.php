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

    public function test_record_deposit_action_opens_and_records_a_deposit(): void
    {
        $user = User::factory()->create();
        Gate::before(fn () => true);
        $branch = BranchFactory::new()->create();
        $user->forceFill(['branch_id' => $branch->id])->save();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));

        // Opening the modal used to fail with "Unknown column patients.display_name".
        Livewire::actingAs($user)
            ->test(ListPatientDeposits::class)
            ->mountAction('recordDeposit')
            ->assertActionMounted('recordDeposit')
            ->callMountedAction()
            ->assertHasFormErrors(['patient_id', 'amount']);

        Livewire::actingAs($user)
            ->test(ListPatientDeposits::class)
            ->callAction('recordDeposit', data: [
                'patient_id' => (string) $patient->id,
                'amount' => '25.00',
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertHasNoActionErrors();

        $this->assertDatabaseHas('patient_deposits', [
            'patient_id' => $patient->id,
            'amount' => '25.00',
        ]);
    }

    public function test_record_deposit_uses_the_session_branch_for_users_without_a_home_branch(): void
    {
        $user = User::factory()->create(['branch_id' => null]);
        Gate::before(fn () => true);
        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));
        session(['current_branch_id' => $branch->id]);

        Livewire::actingAs($user)
            ->test(ListPatientDeposits::class)
            ->callAction('recordDeposit', data: [
                'patient_id' => (string) $patient->id,
                'amount' => '50.00',
                'method' => PaymentMethod::Cash->value,
            ])
            ->assertHasNoActionErrors()
            ->assertNotified('Deposit recorded');

        $this->assertDatabaseHas('patient_deposits', [
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'amount' => '50.00',
        ]);
    }
}
