<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Notification;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Events\UnpaidBillingNoticeRequired;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Notifications\InvoiceUnpaidNotification;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\EmergencyContactFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class UnpaidBillingNotificationsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Appointment', 'Billing']);
    }

    public function test_it_sends_unpaid_notifications_via_notification_fan_out(): void
    {
        Notification::fake();

        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create([
                'branch_id' => $branch->id,
                'phone' => '+233500000001',
                'email' => 'patient@example.com',
            ])
        );

        $billingContact = EmergencyContactFactory::new()->forPatient($patient)->create([
            'phone' => '+233500000002',
            'email' => 'guardian@example.com',
            'notify_for_billing' => true,
            'can_receive_sms' => true,
        ]);

        EmergencyContactFactory::new()->forPatient($patient)->create([
            'phone' => '+233500000003',
            'email' => 'nonbilling@example.com',
            'notify_for_billing' => false,
            'can_receive_sms' => true,
        ]);

        $invoice = Invoice::withoutEvents(function () use ($branch, $patient) {
            return Invoice::query()->withoutGlobalScopes()->create([
                'organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
                'status' => InvoiceStatus::Issued,
                'invoice_type' => InvoiceType::Standalone,
                'currency' => 'GHS',
                'total' => 100,
                'amount_paid' => 0,
            ]);
        });

        event(new UnpaidBillingNoticeRequired($invoice));

        Notification::assertSentTo($patient, InvoiceUnpaidNotification::class);
        Notification::assertSentTo($billingContact, InvoiceUnpaidNotification::class);
    }
}
