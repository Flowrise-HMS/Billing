<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages\ViewInvoice;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Services\DepositRecordingService;
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Models\Branch;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

/**
 * The "Collect payment" header action on the invoice view page must record a
 * cash payment for an issued invoice and leave the invoice paid.
 */
class InvoiceCollectPaymentActionTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Appointment', 'Billing']);
        Mail::fake();
        Gate::before(fn () => true);
    }

    public function test_collect_payment_in_full_marks_invoice_paid(): void
    {
        $branch = BranchFactory::new()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        Context::add('current_branch_id', $branch->id);
        $invoice = $this->issuedInvoice($branch);

        Livewire::actingAs($user)
            ->test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertActionVisible('collectPayment')
            ->callAction('collectPayment', data: [
                'payment_mode' => 'full',
                'payment_method' => PaymentMethod::Cash->value,
                'amount_tendered' => '20',
            ])
            ->assertHasNoActionErrors()
            ->assertNotified(__('Payment recorded'));

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame('10.00', (string) $invoice->amount_paid);
        $this->assertDatabaseHas('payments', [
            'patient_id' => $invoice->patient_id,
            'amount' => '10.00',
        ]);
    }

    public function test_apply_deposit_is_offered_when_the_patient_holds_a_deposit(): void
    {
        $branch = BranchFactory::new()->create();
        $user = User::factory()->create(['branch_id' => $branch->id]);
        Context::add('current_branch_id', $branch->id);
        $invoice = $this->issuedInvoice($branch);

        Livewire::actingAs($user)
            ->test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertActionHidden('applyDeposit');

        app(DepositRecordingService::class)->record(
            patientId: (string) $invoice->patient_id,
            branchId: (string) $branch->id,
            amount: '50.00',
            method: PaymentMethod::Cash,
            recordedBy: $user->id,
        );

        Livewire::actingAs($user)
            ->test(ViewInvoice::class, ['record' => $invoice->getRouteKey()])
            ->assertActionVisible('applyDeposit');
    }

    private function issuedInvoice(Branch $branch): Invoice
    {
        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        return Invoice::withoutEvents(function () use ($branch, $patient): Invoice {
            $invoice = Invoice::query()->withoutGlobalScopes()->create([
                'organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
                'status' => InvoiceStatus::Draft,
                'invoice_type' => InvoiceType::Standalone,
                'currency' => 'GHS',
            ]);

            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'description' => 'UI QA consultation',
                'quantity' => 1,
                'unit_price' => 10,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'line_total' => 10,
                'amount_paid' => 0,
                'line_status' => InvoiceLineStatus::Unpaid,
                'patient_responsibility_amount' => 10,
            ]);

            $invoice = $invoice->fresh(['lines']);
            app(InvoiceIssuanceService::class)->issue($invoice);

            return $invoice->refresh();
        });
    }
}
