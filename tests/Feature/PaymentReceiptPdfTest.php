<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Services\InvoiceAllocationBuilder;
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Billing\Services\PaymentRecordingService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class PaymentReceiptPdfTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        foreach (['Patient', 'Clinical', 'Appointment', 'Billing'] as $module) {
            $this->artisan('module:migrate', ['module' => $module, '--force' => true]);
        }
    }

    public function test_receipt_endpoint_returns_pdf_for_authenticated_user(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $payment = Invoice::withoutEvents(function () use ($branch, $patient) {
            $invoice = Invoice::query()->withoutGlobalScopes()->create([
                'organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'status' => InvoiceStatus::Draft,
                'invoice_type' => InvoiceType::Standalone,
                'currency' => 'GHS',
            ]);

            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'description' => 'Consultation',
                'quantity' => 1,
                'unit_price' => 100,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'line_total' => 100,
                'amount_paid' => 0,
                'line_status' => InvoiceLineStatus::Unpaid,
                'patient_responsibility_amount' => 100,
            ]);

            app(InvoiceIssuanceService::class)->issue($invoice->fresh(['lines']));
            $allocations = app(InvoiceAllocationBuilder::class)->allocateAmountAcrossUnpaidLines(
                $invoice->fresh(['lines']),
                '100'
            );

            return app(PaymentRecordingService::class)->record(
                allocations: $allocations,
                method: PaymentMethod::Cash,
                gateway: 'cash',
                currency: 'GHS',
                patientId: $patient->id,
                branchId: (string) $branch->id,
                recordedBy: null,
            );
        });

        Permission::firstOrCreate(['name' => 'print_receipt', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->givePermissionTo('print_receipt');

        $response = $this->actingAs($user)->get(route('billing.payments.receipt', $payment));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringStartsWith('%PDF', (string) $response->getContent());
    }
}
