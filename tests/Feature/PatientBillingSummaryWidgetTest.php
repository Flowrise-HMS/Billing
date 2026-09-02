<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Livewire\Livewire;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Filament\Widgets\PatientBillingSummaryWidget;
use Modules\Billing\Models\Invoice;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Support\Currency;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PatientBillingSummaryWidgetTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Billing']);

        Permission::findOrCreate('view_patient_balance', 'web');
    }

    protected function tearDown(): void
    {
        Context::forget('current_branch_id');
        parent::tearDown();
    }

    private function patientOwing(string $total, string $paid): Patient
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        Invoice::withoutEvents(fn () => Invoice::query()->withoutGlobalScopes()->create([
            'branch_id' => $branch->id,
            'patient_id' => $patient->id,
            'invoice_number' => 'INV-WGT-'.fake()->unique()->numerify('####'),
            'status' => InvoiceStatus::Issued,
            'currency' => 'GHS',
            'subtotal' => $total,
            'tax_total' => '0',
            'discount_total' => '0',
            'total' => $total,
            'amount_paid' => $paid,
        ]));

        return $patient;
    }

    public function test_shows_the_outstanding_balance_to_a_permitted_user(): void
    {
        $patient = $this->patientOwing('200.00', '50.00');

        Livewire::actingAs(User::factory()->create()->givePermissionTo('view_patient_balance'))
            ->test(PatientBillingSummaryWidget::class, ['patientId' => $patient->id])
            ->assertSee('Outstanding balance')
            ->assertSee(Currency::format('150.00'));
    }

    public function test_renders_nothing_without_the_permission(): void
    {
        $patient = $this->patientOwing('200.00', '0');

        Livewire::actingAs(User::factory()->create())
            ->test(PatientBillingSummaryWidget::class, ['patientId' => $patient->id])
            ->assertDontSee('Outstanding balance');
    }

    public function test_renders_nothing_for_a_blank_patient(): void
    {
        Livewire::actingAs(User::factory()->create()->givePermissionTo('view_patient_balance'))
            ->test(PatientBillingSummaryWidget::class, ['patientId' => null])
            ->assertDontSee('Outstanding balance');
    }
}
