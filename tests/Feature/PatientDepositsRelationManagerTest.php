<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Filament\RelationManagers\PatientDepositsRelationManager;
use Modules\Billing\Services\DepositRecordingService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\Pages\ViewPatient;
use Modules\Patient\Filament\Clusters\Patient\Resources\Patients\PatientResource;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class PatientDepositsRelationManagerTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_relation_manager_is_registered_when_billing_enabled(): void
    {
        $relations = PatientResource::getRelations();
        $this->assertContains(
            'Modules\\Billing\\Filament\\RelationManagers\\PatientDepositsRelationManager',
            $relations,
        );
    }

    public function test_relation_manager_lists_patient_deposits(): void
    {
        $user = User::factory()->create();
        Gate::before(fn () => true);
        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));

        app(DepositRecordingService::class)->record(
            patientId: (string) $patient->id,
            branchId: (string) $branch->id,
            amount: '75.00',
            method: PaymentMethod::Cash,
            recordedBy: $user->id,
        );

        Livewire::actingAs($user)
            ->test(PatientDepositsRelationManager::class, [
                'ownerRecord' => $patient,
                'pageClass' => ViewPatient::class,
            ])
            ->assertOk();
    }
}
