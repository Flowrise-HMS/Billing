<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Events\UnpaidBillingNoticeRequired;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Services\InvoiceAllocationBuilder;
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Billing\Services\InvoiceTotalsService;
use Modules\Billing\Services\ManualInvoiceService;
use Modules\Billing\Services\PaymentRecordingService;
use Modules\Core\Models\Branch;
use Tests\TestCase;

class InvoiceIssuanceRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('module:migrate', ['module' => 'Core', '--force' => true]);
        $this->artisan('module:migrate', ['module' => 'Billing', '--force' => true]);
    }

    public function test_issue_and_pay_same_transaction_does_not_fire_unpaid_notice_when_balance_is_zero(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $branch = Branch::factory()->create(['is_active' => true]);

        $invoice = app(ManualInvoiceService::class)->createStandaloneDraft(
            branchId: $branch->id,
            patientId: null,
            currency: 'GHS',
            invoiceType: InvoiceType::Standalone,
        );

        $invoice->guest_name = 'Test Guest';
        $invoice->save();

        InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Test item',
            'quantity' => 1,
            'unit_price' => '100.00',
            'line_total' => '100.00',
            'amount_paid' => '0',
            'line_status' => InvoiceLineStatus::Unpaid,
            'patient_responsibility_amount' => '100.00',
        ]);

        // Recalculate totals
        $invoice = app(InvoiceTotalsService::class)
            ->recalculate($invoice->fresh(['lines']));

        Event::fake();

        DB::transaction(function () use ($invoice) {
            $issued = app(InvoiceIssuanceService::class)->issue($invoice->fresh(['lines']));

            $allocations = app(InvoiceAllocationBuilder::class)
                ->allocateAmountAcrossUnpaidLines($issued, $issued->total);

            app(PaymentRecordingService::class)->record(
                allocations: $allocations,
                method: PaymentMethod::Cash,
                gateway: 'cash',
                currency: 'GHS',
                patientId: null,
                branchId: (string) $invoice->branch_id,
                recordedBy: auth()->id(),
            );
        });

        Event::assertNotDispatched(UnpaidBillingNoticeRequired::class);
    }
}
