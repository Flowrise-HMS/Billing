<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Models\Invoice;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_invoice_pdf_returns_pdf_for_user_with_permission(): void
    {
        Permission::firstOrCreate(['name' => 'view_invoice_pdf', 'guard_name' => 'web']);

        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $invoice = Invoice::withoutEvents(function () use ($branch, $patient) {
            return Invoice::query()->withoutGlobalScopes()->create([
                'organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
                'status' => InvoiceStatus::Draft,
                'invoice_type' => InvoiceType::Standalone,
                'currency' => 'GHS',
            ]);
        });

        $user = User::factory()->create();
        $user->givePermissionTo('view_invoice_pdf');

        $response = $this->actingAs($user)->get(route('billing.invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }

    public function test_invoice_pdf_returns_403_without_permission(): void
    {
        Permission::firstOrCreate(['name' => 'view_invoice_pdf', 'guard_name' => 'web']);

        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $invoice = Invoice::withoutEvents(function () use ($branch, $patient) {
            return Invoice::query()->withoutGlobalScopes()->create([
                'organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
                'status' => InvoiceStatus::Draft,
                'invoice_type' => InvoiceType::Standalone,
                'currency' => 'GHS',
            ]);
        });

        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('billing.invoices.pdf', $invoice));

        $response->assertForbidden();
    }
}
