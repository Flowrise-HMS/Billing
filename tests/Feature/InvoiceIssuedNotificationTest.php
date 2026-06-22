<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Notification;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Notifications\InvoiceIssuedNotification;
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Billing\Services\InvoiceTotalsService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\EmergencyContactFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class InvoiceIssuedNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Billing']);
    }

    public function test_issuing_invoice_notifies_patient_and_billing_emergency_contact_via_mail_and_sms(): void
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
                'status' => InvoiceStatus::Draft,
                'invoice_type' => InvoiceType::Standalone,
                'currency' => 'GHS',
                'total' => 0,
                'amount_paid' => 0,
            ]);
        });

        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'billable_type' => null,
            'billable_id' => null,
            'description' => 'Service',
            'quantity' => 1,
            'unit_price' => 100,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => 100,
            'amount_paid' => 0,
            'line_status' => InvoiceLineStatus::Unpaid,
            'patient_responsibility_amount' => 100,
        ]);

        app(InvoiceTotalsService::class)->recalculate($invoice->fresh(['lines']));

        app(InvoiceIssuanceService::class)->issue($invoice->fresh(['lines']));

        Notification::assertSentTo($patient, InvoiceIssuedNotification::class);
        Notification::assertSentTo($billingContact, InvoiceIssuedNotification::class);
    }
}
