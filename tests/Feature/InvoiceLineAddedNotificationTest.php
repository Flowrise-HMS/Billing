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
use Modules\Billing\Notifications\InvoiceLineAddedNotification;
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Billing\Services\InvoiceTotalsService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class InvoiceLineAddedNotificationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    public function test_line_added_on_issued_invoice_sends_notification(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create([
                'branch_id' => $branch->id,
                'phone' => '+233500000001',
                'email' => 'patient@example.com',
            ])
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
                'total' => 0,
                'amount_paid' => 0,
            ]);
        });

        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'billable_type' => null,
            'billable_id' => null,
            'description' => 'First service',
            'quantity' => 1,
            'unit_price' => 40,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => 40,
            'amount_paid' => 0,
            'line_status' => InvoiceLineStatus::Unpaid,
            'patient_responsibility_amount' => 40,
        ]);

        app(InvoiceTotalsService::class)->recalculate($invoice->fresh(['lines']));
        app(InvoiceIssuanceService::class)->issue($invoice->fresh(['lines']));

        Notification::fake();

        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'billable_type' => null,
            'billable_id' => null,
            'description' => 'Second service',
            'quantity' => 1,
            'unit_price' => 10,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => 10,
            'amount_paid' => 0,
            'line_status' => InvoiceLineStatus::Unpaid,
            'patient_responsibility_amount' => 10,
        ]);

        Notification::assertSentTo($patient, InvoiceLineAddedNotification::class);
    }

    public function test_line_added_on_draft_invoice_does_not_notify(): void
    {
        Notification::fake();

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
                'total' => 0,
                'amount_paid' => 0,
            ]);
        });

        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'billable_type' => null,
            'billable_id' => null,
            'description' => 'Draft line',
            'quantity' => 1,
            'unit_price' => 25,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => 25,
            'amount_paid' => 0,
            'line_status' => InvoiceLineStatus::Unpaid,
            'patient_responsibility_amount' => 25,
        ]);

        Notification::assertNotSentTo($patient, InvoiceLineAddedNotification::class);
    }
}
