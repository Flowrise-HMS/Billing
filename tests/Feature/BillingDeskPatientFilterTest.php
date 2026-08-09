<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Livewire\Livewire;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Filament\Clusters\Billing\Pages\BillingDesk;
use Modules\Billing\Models\Invoice;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BillingDeskPatientFilterTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Appointment', 'Billing']);
    }

    public function test_patient_filter_is_searchable_select_and_filters_invoices(): void
    {
        $branch = BranchFactory::new()->create(['is_default' => true]);
        Context::add('current_branch_id', $branch->id);

        Permission::findOrCreate('View BillingDesk', 'web');

        $user = User::factory()->create([
            'branch_id' => $branch->id,
        ])->givePermissionTo('View BillingDesk');

        $patientA = Patient::withoutEvents(
            fn () => PatientFactory::new()->create([
                'branch_id' => $branch->id,
                'first_name' => 'Ama',
                'last_name' => 'Mensah',
                'middle_name' => null,
                'mrn' => 'FR-BILL-AMA-001',
            ])
        );

        $patientB = Patient::withoutEvents(
            fn () => PatientFactory::new()->create([
                'branch_id' => $branch->id,
                'first_name' => 'Kojo',
                'last_name' => 'Asante',
                'middle_name' => null,
                'mrn' => 'FR-BILL-KOJO-002',
            ])
        );

        $invoiceA = Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'patient_id' => $patientA->id,
            'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
            'status' => InvoiceStatus::Issued,
            'invoice_type' => InvoiceType::Standalone,
            'currency' => 'GHS',
            'total' => 100,
            'amount_paid' => 0,
            'issued_at' => now(),
        ]));

        $invoiceB = Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
            'organization_id' => $branch->organization_id,
            'branch_id' => $branch->id,
            'patient_id' => $patientB->id,
            'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
            'status' => InvoiceStatus::Issued,
            'invoice_type' => InvoiceType::Standalone,
            'currency' => 'GHS',
            'total' => 50,
            'amount_paid' => 0,
            'issued_at' => now(),
        ]));

        Livewire::actingAs($user)
            ->test(BillingDesk::class)
            ->assertTableFilterExists('patient_id')
            ->assertCanSeeTableRecords([$invoiceA, $invoiceB])
            ->filterTable('patient_id', $patientA->id)
            ->assertCanSeeTableRecords([$invoiceA])
            ->assertCanNotSeeTableRecords([$invoiceB]);
    }
}
