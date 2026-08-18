<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PatientDepositStatus;
use Modules\Billing\Filament\Clusters\Billing\Widgets\OutstandingReceivablesTableWidget;
use Modules\Billing\Filament\Clusters\Billing\Widgets\PatientDepositBalancesTableWidget;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PatientDeposit;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class DepositsAndOutstandingWidgetsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_deposit_balances_widget_is_searchable_and_branch_filterable(): void
    {
        $user = User::factory()->create();
        Gate::before(fn () => true);

        $branchA = BranchFactory::new()->create();
        $branchB = BranchFactory::new()->create();

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create([
                'branch_id' => $branchA->id,
                'first_name' => 'Ama',
                'last_name' => 'Mensah',
                'mrn' => 'FR-DEP-AMA-001',
            ])
        );

        $deposit = PatientDeposit::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branchA->id,
            'amount' => '150.00',
            'unallocated_balance' => '60.00',
            'status' => PatientDepositStatus::Active,
            'recorded_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(PatientDepositBalancesTableWidget::class)
            ->assertCanSeeTableRecords([$deposit])
            ->searchTable('Ama')
            ->assertCanSeeTableRecords([$deposit])
            ->searchTable('NoMatchXYZ')
            ->assertCanNotSeeTableRecords([$deposit])
            ->searchTable('')
            ->filterTable('branch_id', $branchA->id)
            ->assertCanSeeTableRecords([$deposit])
            ->filterTable('branch_id', $branchB->id)
            ->assertCanNotSeeTableRecords([$deposit]);
    }

    public function test_outstanding_widget_lists_unpaid_invoices_and_is_searchable(): void
    {
        $user = User::factory()->create();
        Gate::before(fn () => true);

        $branch = BranchFactory::new()->create();

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create([
                'branch_id' => $branch->id,
                'first_name' => 'Kojo',
                'last_name' => 'Asante',
                'mrn' => 'FR-OUT-KOJO-002',
            ])
        );

        $invoice = Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
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

        Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
            'status' => InvoiceStatus::Paid,
            'invoice_type' => InvoiceType::Standalone,
            'currency' => 'GHS',
            'total' => 20,
            'amount_paid' => 20,
            'issued_at' => now(),
        ]));

        Livewire::actingAs($user)
            ->test(OutstandingReceivablesTableWidget::class)
            ->assertCanSeeTableRecords([$invoice])
            ->searchTable('Kojo')
            ->assertCanSeeTableRecords([$invoice])
            ->searchTable('NoMatchXYZ')
            ->assertCanNotSeeTableRecords([$invoice]);
    }
}
