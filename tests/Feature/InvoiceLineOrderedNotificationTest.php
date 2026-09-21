<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Log;
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

    public function test_ordered_notification_is_on_by_default_for_a_reachable_patient(): void
    {
        // Notification::fake() evaluates via() eagerly for queued notifications
        // too, but the channel list is what matters here: assert it directly.
        [$item, $patient] = $this->seedRequestItem(isMedication: false, unitPrice: '45.00');
        $line = $this->syncedLine($item);

        $this->assertSame(['mail', 'sms'], (new InvoiceLineOrderedNotification($line))->via($patient->fresh()));
    }

    public function test_disabling_both_toggles_produces_no_channels(): void
    {
        NotificationSettings::fake(['invoice_line_ordered_mail' => false, 'invoice_line_ordered_sms' => false]);

        [$item, $patient] = $this->seedRequestItem(isMedication: false, unitPrice: '45.00');
        $line = $this->syncedLine($item);

        $this->assertSame([], (new InvoiceLineOrderedNotification($line))->via($patient->fresh()));
    }

    public function test_patient_without_email_gets_sms_only_and_a_skip_is_logged_when_unreachable(): void
    {
        [$item, $patient] = $this->seedRequestItem(isMedication: false, unitPrice: '45.00');
        $line = $this->syncedLine($item);
        $notification = new InvoiceLineOrderedNotification($line);

        $patient->forceFill(['email' => null])->saveQuietly();
        $this->assertSame(['sms'], $notification->via($patient->fresh()));

        Log::spy();
        $patient->forceFill(['phone' => null])->saveQuietly();
        $this->assertSame([], $notification->via($patient->fresh()));

        Log::shouldHaveReceived('warning')->withArgs(fn (string $message, array $context): bool => $message === 'notification.skipped'
            && $context['reason'] === 'no_route'
            && $context['notifiable_id'] === $patient->id
            && $context['has_email'] === false
            && $context['has_phone'] === false);
    }

    public function test_lab_order_on_draft_invoice_notifies_with_settings_on(): void
    {
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

    protected function syncedLine(RequestItem $item): \Modules\Billing\Models\InvoiceLine
    {
        app(InvoiceLineSyncService::class)->syncFromRequestItem($item->fresh([
            'serviceRequest.encounter',
            'service.category',
            'prescriptionDetail',
        ]));

        return Invoice::query()->withoutGlobalScopes()
            ->where('encounter_id', $item->serviceRequest->encounter_id)
            ->firstOrFail()
            ->lines()
            ->firstOrFail();
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
