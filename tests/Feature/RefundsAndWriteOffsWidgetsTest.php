<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Filament\Clusters\Billing\Widgets\RefundsTableWidget;
use Modules\Billing\Filament\Clusters\Billing\Widgets\WriteOffsTableWidget;
use Modules\Billing\Models\Payment;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class RefundsAndWriteOffsWidgetsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_refunds_widget_is_searchable_by_client_and_reason(): void
    {
        $user = User::factory()->create();
        Gate::before(fn () => true);

        $branch = BranchFactory::new()->create();

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create([
                'branch_id' => $branch->id,
                'first_name' => 'Ama',
                'last_name' => 'Mensah',
                'mrn' => 'FR-RF-AMA-001',
            ])
        );

        $refund = Payment::create([
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
            ->test(RefundsTableWidget::class)
            ->assertCanSeeTableRecords([$refund])
            ->searchTable('Ama')
            ->assertCanSeeTableRecords([$refund])
            ->searchTable('Overcharge')
            ->assertCanSeeTableRecords([$refund])
            ->searchTable('NoMatchXYZ')
            ->assertCanNotSeeTableRecords([$refund]);
    }

    public function test_write_offs_widget_is_searchable_by_client(): void
    {
        $user = User::factory()->create();
        Gate::before(fn () => true);

        $branch = BranchFactory::new()->create();

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create([
                'branch_id' => $branch->id,
                'first_name' => 'Kojo',
                'last_name' => 'Asante',
                'mrn' => 'FR-WO-KOJO-002',
            ])
        );

        $writeOff = Payment::create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'method' => PaymentMethod::Cash,
            'type' => PaymentType::WriteOff,
            'amount' => '-50.00',
            'currency' => 'GHS',
            'provider_transaction_id' => 'txn-'.Str::uuid(),
            'received_at' => now(),
            'metadata' => ['reason' => 'Uncollectible'],
        ]);

        Livewire::actingAs($user)
            ->test(WriteOffsTableWidget::class)
            ->assertCanSeeTableRecords([$writeOff])
            ->searchTable('Kojo')
            ->assertCanSeeTableRecords([$writeOff])
            ->searchTable('NoMatchXYZ')
            ->assertCanNotSeeTableRecords([$writeOff]);
    }
}
