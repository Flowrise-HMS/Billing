<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\InvoiceLineSyncService;
use Modules\Clinical\Database\Factories\EncounterFactory;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Enums\ServiceCategoryCode;
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceCategory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Models\PrescriptionDetail;
use Tests\TestCase;

class MedicationInvoiceIssuanceTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Pharmacy', 'Billing']);
    }

    public function test_syncing_medication_request_item_issues_encounter_invoice(): void
    {
        [$item] = $this->seedRequestItem(isMedication: true, unitPrice: '1.50');

        app(InvoiceLineSyncService::class)->syncFromRequestItem($item->fresh([
            'serviceRequest.encounter',
            'service.category',
            'prescriptionDetail',
        ]));

        $invoice = Invoice::query()->withoutGlobalScopes()
            ->where('encounter_id', $item->serviceRequest->encounter_id)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
        $this->assertNotNull($invoice->issued_at);
        $this->assertTrue(bccomp((string) $invoice->total, '0', 2) > 0);
    }

    public function test_syncing_non_medication_request_item_leaves_invoice_draft(): void
    {
        [$item] = $this->seedRequestItem(isMedication: false, unitPrice: '20.00');

        app(InvoiceLineSyncService::class)->syncFromRequestItem($item->fresh([
            'serviceRequest.encounter',
            'service.category',
            'prescriptionDetail',
        ]));

        $invoice = Invoice::query()->withoutGlobalScopes()
            ->where('encounter_id', $item->serviceRequest->encounter_id)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatus::Draft, $invoice->status);
        $this->assertNull($invoice->issued_at);
    }

    /**
     * @return array{RequestItem}
     */
    protected function seedRequestItem(bool $isMedication, string $unitPrice): array
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $encounter = EncounterFactory::new()
            ->forPatient($patient)
            ->create([
                'branch_id' => $branch->id,
                'status' => EncounterStatus::ARRIVED,
            ]);

        $category = ServiceCategory::query()->firstOrCreate(
            ['code' => $isMedication ? ServiceCategoryCode::MED->value : ServiceCategoryCode::CON->value],
            [
                'name' => $isMedication ? 'Medication' : 'Consultation',
                'is_active' => true,
            ]
        );

        $service = Service::factory()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'price' => $unitPrice,
            'name' => $isMedication ? 'Panadol 500mg' : 'General Consultation',
        ]);

        $request = ServiceRequest::factory()->create([
            'patient_id' => $patient->id,
            'encounter_id' => $encounter->id,
            'branch_id' => $branch->id,
        ]);

        $item = RequestItem::factory()->create([
            'service_request_id' => $request->id,
            'service_id' => $service->id,
            'quantity' => 1,
            'unit_price' => $unitPrice,
            'total_price' => $unitPrice,
            'status' => 'pending',
        ]);

        if ($isMedication) {
            PrescriptionDetail::create([
                'request_item_id' => $item->id,
                'frequency' => 'qd',
                'duration_days' => 2,
                'route' => 'po',
                'dose_amount' => 1,
                'prn' => false,
                'administration_context' => AdministrationContext::IN_FACILITY,
                'course_started_at' => now(),
                'course_end_at' => now()->addDays(2),
                'total_administrations' => 2,
            ]);
        }

        return [$item->fresh()];
    }
}
