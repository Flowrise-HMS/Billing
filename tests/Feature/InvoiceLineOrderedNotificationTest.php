<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Notification;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Notifications\InvoiceLineAddedNotification;
use Modules\Billing\Notifications\InvoiceLineOrderedNotification;
use Modules\Billing\Services\InvoiceLineSyncService;
use Modules\Clinical\Database\Factories\EncounterFactory;
use Modules\Clinical\Enums\EncounterStatus;
use Modules\Clinical\Models\RequestItem;
use Modules\Clinical\Models\ServiceRequest;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Enums\ServiceCategoryCode;
use Modules\Core\Models\Service;
use Modules\Core\Models\ServiceCategory;
use Modules\Core\Settings\NotificationSettings;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Modules\Pharmacy\Enums\AdministrationContext;
use Modules\Pharmacy\Models\PrescriptionDetail;
use Tests\TestCase;

class InvoiceLineOrderedNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Pharmacy', 'Billing']);
    }

    public function test_settings_off_by_default_produces_no_channels(): void
    {
        // Notification::fake()'s assertNothingSent() can't be used here: Laravel
        // queues a ShouldQueue notification unconditionally and only evaluates
        // via() later, inside the queued job — so it would "look sent" to the
        // fake even with every channel disabled. Assert via() directly instead.
        [$item] = $this->seedRequestItem(isMedication: false, unitPrice: '45.00');

        app(InvoiceLineSyncService::class)->syncFromRequestItem($item->fresh([
            'serviceRequest.encounter',
            'service.category',
            'prescriptionDetail',
        ]));

        $invoice = Invoice::query()->withoutGlobalScopes()
            ->where('encounter_id', $item->serviceRequest->encounter_id)
            ->first();
        $line = $invoice->lines()->firstOrFail();

        $notification = new InvoiceLineOrderedNotification($line);
        $this->assertSame([], $notification->via($invoice->patient));
    }

    public function test_lab_order_on_draft_invoice_notifies_with_settings_on(): void
    {
        $this->enableOrderedNotifications();
        Notification::fake();

        [$item, $patient] = $this->seedRequestItem(isMedication: false, unitPrice: '45.00');

        app(InvoiceLineSyncService::class)->syncFromRequestItem($item->fresh([
            'serviceRequest.encounter',
            'service.category',
            'prescriptionDetail',
        ]));

        $invoice = Invoice::query()->withoutGlobalScopes()
            ->where('encounter_id', $item->serviceRequest->encounter_id)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertTrue($invoice->isDraft(), 'The order should land on a Draft invoice, not get auto-issued (that is medication-only behavior).');

        Notification::assertSentTo($patient, InvoiceLineOrderedNotification::class);
        Notification::assertNotSentTo($patient, InvoiceLineAddedNotification::class);
    }

    public function test_medication_order_does_not_send_the_order_notification(): void
    {
        $this->enableOrderedNotifications();
        Notification::fake();

        [$item, $patient] = $this->seedRequestItem(isMedication: true, unitPrice: '1.50');

        app(InvoiceLineSyncService::class)->syncFromRequestItem($item->fresh([
            'serviceRequest.encounter',
            'service.category',
            'prescriptionDetail',
        ]));

        // Medications auto-issue their invoice and already get InvoiceIssuedNotification
        // with a whole-invoice pay link (Modules/Billing/app/Listeners/SendInvoiceIssuedNotifications.php);
        // sending InvoiceLineOrderedNotification too would double-notify for the same order.
        Notification::assertNotSentTo($patient, InvoiceLineOrderedNotification::class);
    }

    protected function enableOrderedNotifications(): void
    {
        $settings = app(NotificationSettings::class);
        $settings->invoice_line_ordered_mail = true;
        $settings->invoice_line_ordered_sms = true;
        $settings->save();
    }

    /**
     * @return array{0: RequestItem, 1: Patient}
     */
    protected function seedRequestItem(bool $isMedication, string $unitPrice): array
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create([
                'branch_id' => $branch->id,
                'phone' => '+233500000002',
                'email' => 'order-notify@example.com',
            ])
        );

        $encounter = EncounterFactory::new()
            ->forPatient($patient)
            ->create([
                'branch_id' => $branch->id,
                'status' => EncounterStatus::ARRIVED,
            ]);

        $category = ServiceCategory::query()->firstOrCreate(
            ['code' => $isMedication ? ServiceCategoryCode::MED->value : ServiceCategoryCode::LAB->value],
            [
                'name' => $isMedication ? 'Medication' : 'Laboratory',
                'is_active' => true,
            ]
        );

        $service = Service::factory()->create([
            'branch_id' => $branch->id,
            'category_id' => $category->id,
            'price' => $unitPrice,
            'name' => $isMedication ? 'Panadol 500mg' : 'Full Blood Count',
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

        return [$item->fresh(), $patient];
    }
}
