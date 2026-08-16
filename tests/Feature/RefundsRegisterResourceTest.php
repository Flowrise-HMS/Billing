<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Filament\Clusters\Billing\Resources\RefundsRegister\Pages\ListRefunds;
use Modules\Billing\Models\Payment;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class RefundsRegisterResourceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_list_only_shows_refunds_with_absolute_amount(): void
    {
        $user = User::factory()->create();
        Gate::before(fn () => true);
        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));

        Payment::create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'method' => PaymentMethod::Gateway,
            'gateway' => 'refund',
            'type' => PaymentType::Refund,
            'amount' => '-25.00',
            'currency' => 'GHS',
            'provider_transaction_id' => 'txn-'.Str::uuid(),
            'received_at' => now(),
            'metadata' => ['reason' => 'Overcharge', 'original_payment_id' => 'orig-1'],
        ]);

        Livewire::actingAs($user)
            ->test(ListRefunds::class)
            ->assertOk()
            ->assertSee('25.00');
    }
}
