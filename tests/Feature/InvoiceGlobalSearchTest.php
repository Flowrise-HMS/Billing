<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;
use Modules\Billing\Models\Invoice;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class InvoiceGlobalSearchTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Patient', 'Billing']);

        Permission::findOrCreate('ViewAny Invoice', 'web');
        Permission::findOrCreate('View Invoice', 'web');
    }

    public function test_invoices_are_found_by_number_and_patient_name(): void
    {
        $branch = BranchFactory::new()->create();
        $this->actingAs(
            User::factory()->create(['branch_id' => $branch->id])
                ->givePermissionTo('ViewAny Invoice', 'View Invoice')
        );

        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create([
            'branch_id' => $branch->id,
            'title' => null,
            'first_name' => 'Zainab',
            'middle_name' => null,
            'last_name' => 'Fuseini',
        ]));

        Invoice::factory()->create([
            'patient_id' => $patient->id,
            'branch_id' => $branch->id,
            'invoice_number' => 'INV-GS-0001',
        ]);

        $byNumber = InvoiceResource::getGlobalSearchResults('INV-GS-0001');
        $this->assertCount(1, $byNumber);
        $this->assertSame('INV-GS-0001', $byNumber->first()->title);
        $this->assertSame('Zainab Fuseini', $byNumber->first()->details['Patient'] ?? null);

        $this->assertCount(1, InvoiceResource::getGlobalSearchResults('Fuseini'));
        $this->assertCount(0, InvoiceResource::getGlobalSearchResults('no-such-invoice'));
    }
}
