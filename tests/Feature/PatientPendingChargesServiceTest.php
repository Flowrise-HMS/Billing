<?php

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Events\PaymentConfirmed;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Services\PatientPendingChargesService;
use Modules\Billing\Settings\BillingSettings;
use Modules\Clinical\Database\Factories\EncounterFactory;
use Modules\Clinical\Database\Factories\RequestItemFactory;
use Modules\Clinical\Database\Factories\ServiceRequestFactory;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Database\Factories\ServiceFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->migrateModules(['Core', 'Patient', 'Clinical', 'Billing']);

    $settings = app(BillingSettings::class);
    $settings->financial_hold_enabled = true;
    $settings->save();

    $this->branch = BranchFactory::new()->create();
    $this->patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $this->branch->id]));
    $this->user = User::factory()->create(['branch_id' => null]);
    $this->encounter = EncounterFactory::new()->create(['patient_id' => $this->patient->id, 'branch_id' => $this->branch->id]);
});

/**
 * An ordered service. Clinical's order bridge bills it as an unpaid request-item line on
 * the encounter's draft invoice, exactly as happens in production.
 *
 * @return array{0: RequestItem, 1: InvoiceLine, 2: Invoice}
 */
function orderedCharge(float $amount = 50, bool $prepay = true): array
{
    $test = test();

    $service = ServiceFactory::new()->create(['requires_payment_before' => $prepay, 'price' => $amount]);

    $request = ServiceRequestFactory::new()->create([
        'patient_id' => $test->patient->id,
        'branch_id' => $test->branch->id,
        'encounter_id' => $test->encounter->id,
    ]);

    $item = RequestItemFactory::new()->create([
        'service_request_id' => $request->id,
        'service_id' => $service->id,
        'unit_price' => $amount,
        'total_price' => $amount,
    ]);

    $line = InvoiceLine::query()
        ->where('billable_type', (new RequestItem)->getMorphClass())
        ->where('billable_id', $item->id)
        ->firstOrFail();

    return [$item->fresh(), $line, $line->invoice()->withoutGlobalScopes()->firstOrFail()];
}

it('lists only unpaid request-item lines for the patient', function (): void {
    [, $pendingLine] = orderedCharge(50);
    [, $paidLine] = orderedCharge(30);
    $paidLine->update(['amount_paid' => 30, 'line_status' => InvoiceLineStatus::Paid]);

    $otherPatient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $this->branch->id]));
    $otherInvoice = Invoice::query()->withoutGlobalScopes()->create([
        'organization_id' => $this->branch->organization_id,
        'branch_id' => $this->branch->id,
        'patient_id' => $otherPatient->id,
        'invoice_number' => Invoice::generateInvoiceNumber((string) $this->branch->id),
        'status' => InvoiceStatus::Issued,
        'invoice_type' => InvoiceType::Standalone,
        'currency' => 'GHS',
        'total' => 20,
        'amount_paid' => 0,
    ]);
    InvoiceLine::query()->create([
        'invoice_id' => $otherInvoice->id,
        'billable_type' => null,
        'billable_id' => null,
        'service_id' => ServiceFactory::new()->create()->id,
        'description' => 'Walk-in item',
        'quantity' => 1,
        'unit_price' => 20,
        'discount_amount' => 0,
        'tax_amount' => 0,
        'amount_paid' => 0,
        'line_status' => InvoiceLineStatus::Unpaid,
        'patient_responsibility_amount' => 20,
    ]);

    $rows = app(PatientPendingChargesService::class)->rowsForPatient($this->patient->id);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['invoice_line_id'])->toBe((string) $pendingLine->id)
        ->and($rows->first()['remaining'])->toBe('50.00')
        ->and($rows->first()['requires_payment_before'])->toBeTrue()
        ->and($rows->first()['order_status'])->toBe('pending');
});

it('still lists a completed order that was never paid, marked with its status', function (): void {
    [$item] = orderedCharge(50, prepay: false);
    $item->update(['status' => 'completed']);

    $rows = app(PatientPendingChargesService::class)->rowsForPatient($this->patient->id);

    expect($rows)->toHaveCount(1)
        ->and($rows->first()['order_status'])->toBe('completed');
});

it('settles pending lines on the existing invoice so the order is released', function (): void {
    Event::fake([PaymentConfirmed::class]);

    [$item, $line, $invoice] = orderedCharge(50);

    expect($item->hasActiveFinancialHold())->toBeTrue()
        ->and($item->payment_status)->toBe(InvoiceLineStatus::Unpaid);

    $payments = app(PatientPendingChargesService::class)->settle(
        lineIds: [$line->id],
        patientId: $this->patient->id,
        branchId: $this->branch->id,
        method: PaymentMethod::Cash,
        currency: 'GHS',
        recordedBy: $this->user->id,
        metadata: ['source' => 'pharmacy_pos'],
    );

    $line->refresh();
    $invoice->refresh();

    expect($payments)->toHaveCount(1)
        ->and((string) $payments->first()->amount)->toBe('50.00')
        ->and($line->line_status)->toBe(InvoiceLineStatus::Paid)
        ->and($invoice->status)->toBe(InvoiceStatus::Paid)
        ->and($invoice->issued_at)->not->toBeNull()
        ->and($item->fresh()->payment_status)->toBe(InvoiceLineStatus::Paid)
        ->and($item->fresh()->hasActiveFinancialHold())->toBeFalse()
        ->and(InvoiceLine::query()->where('billable_id', $item->id)->count())->toBe(1);

    Event::assertDispatched(PaymentConfirmed::class);
});

it('records one payment per invoice when charges span invoices', function (): void {
    [, $lineA, $invoiceA] = orderedCharge(50);
    [, $lineB, $invoiceB] = orderedCharge(30);

    // Move the second charge onto a separate (issued) invoice.
    $invoiceB = Invoice::query()->withoutGlobalScopes()->create([
        'organization_id' => $this->branch->organization_id,
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'invoice_number' => Invoice::generateInvoiceNumber((string) $this->branch->id),
        'status' => InvoiceStatus::Issued,
        'invoice_type' => InvoiceType::Standalone,
        'currency' => 'GHS',
        'total' => 0,
        'amount_paid' => 0,
    ]);
    $lineB->update(['invoice_id' => $invoiceB->id]);
    $invoiceA->refresh();

    $payments = app(PatientPendingChargesService::class)->settle(
        lineIds: [$lineA->id, $lineB->id],
        patientId: $this->patient->id,
        branchId: $this->branch->id,
        method: PaymentMethod::Cash,
        currency: 'GHS',
        recordedBy: $this->user->id,
    );

    expect($payments)->toHaveCount(2)
        ->and($invoiceA->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoiceB->fresh()->status)->toBe(InvoiceStatus::Paid)
        ->and($invoiceA->id)->not->toBe($invoiceB->id)
        ->and(app(PatientPendingChargesService::class)->rowsForPatient($this->patient->id))->toBeEmpty();
});

it('refuses lines that are already paid or belong to another patient', function (): void {
    [, $line] = orderedCharge(50);
    $line->update(['amount_paid' => 50, 'line_status' => InvoiceLineStatus::Paid]);

    $service = app(PatientPendingChargesService::class);

    expect($service->remainingFor([$line->id], $this->patient->id))->toBeNull();

    expect(fn () => $service->settle(
        lineIds: [$line->id],
        patientId: $this->patient->id,
        branchId: $this->branch->id,
        method: PaymentMethod::Cash,
        currency: 'GHS',
        recordedBy: $this->user->id,
    ))->toThrow(InvalidArgumentException::class);

    [, $pendingLine] = orderedCharge(20);
    $stranger = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $this->branch->id]));

    expect($service->remainingFor([$pendingLine->id], $stranger->id))->toBeNull();
});
