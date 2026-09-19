<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\Payment;
use Modules\Core\Support\OptionalClass;

/**
 * The charges a patient already owes for ordered services and medications: the
 * request-item lines on their invoices that are not yet paid. Settling them here goes
 * through the same allocation and payment path the billing desk uses, so the order's
 * payment status and any financial hold clear exactly as they do at the desk.
 */
class PatientPendingChargesService
{
    public function __construct(
        protected InvoiceIssuanceService $issuanceService,
        protected InvoiceAllocationBuilder $allocationBuilder,
        protected PaymentRecordingService $paymentRecordingService,
    ) {}

    /**
     * @return Collection<int, InvoiceLine>
     */
    public function forPatient(string $patientId, ?string $branchId = null): Collection
    {
        return $this->pendingLinesQuery($patientId, $branchId)
            ->with(['invoice', 'billable.service', 'billable.prescriptionDetail'])
            ->orderBy('created_at')
            ->get();
    }

    /**
     * Presentation-friendly rows for a POS or cashier screen.
     *
     * @return Collection<int, array{invoice_line_id: string, invoice_id: string, invoice_number: ?string, invoice_status: string, request_item_id: string, service_id: ?string, name: string, quantity: int, remaining: string, requires_payment_before: bool, is_medication: bool, order_status: ?string}>
     */
    public function rowsForPatient(string $patientId, ?string $branchId = null): Collection
    {
        return $this->forPatient($patientId, $branchId)->map(fn (InvoiceLine $line): array => [
            'invoice_line_id' => (string) $line->id,
            'invoice_id' => (string) $line->invoice_id,
            'invoice_number' => $line->invoice?->invoice_number,
            'invoice_status' => $line->invoice?->status?->value ?? '',
            'request_item_id' => (string) $line->billable_id,
            'service_id' => $line->service_id !== null ? (string) $line->service_id : null,
            'name' => $line->description ?: ($line->billable?->service?->name ?? 'Service'),
            'quantity' => (int) $line->quantity,
            'remaining' => $line->remainingAmount(),
            'requires_payment_before' => (bool) ($line->billable?->service?->requires_payment_before ?? false),
            'is_medication' => $line->billable?->prescriptionDetail !== null,
            'order_status' => $this->orderStatus($line),
        ])->values();
    }

    /**
     * Raw status of the order behind the line (e.g. pending, in_progress, completed), so a
     * cashier can tell "waiting for payment before service" from "done, still owed".
     */
    protected function orderStatus(InvoiceLine $line): ?string
    {
        $status = $line->billable?->status ?? null;

        return $status instanceof \BackedEnum ? (string) $status->value : ($status === null ? null : (string) $status);
    }

    /**
     * Pay the given pending lines in full. Draft invoices are issued first (payments
     * can only be recorded on issued invoices); one payment is recorded per invoice.
     *
     * @param  list<string>  $lineIds
     * @param  array<string, mixed>  $metadata
     * @return Collection<int, Payment>
     */
    public function settle(
        array $lineIds,
        string $patientId,
        string $branchId,
        PaymentMethod $method,
        string $currency,
        ?int $recordedBy,
        array $metadata = [],
    ): Collection {
        $lineIds = array_values(array_unique(array_map('strval', $lineIds)));

        if ($lineIds === []) {
            return collect();
        }

        return DB::transaction(function () use ($lineIds, $patientId, $branchId, $method, $currency, $recordedBy, $metadata): Collection {
            $lines = $this->pendingLinesQuery($patientId, null)
                ->whereIn('id', $lineIds)
                ->with('invoice')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (InvoiceLine $line): string => (string) $line->id);

            foreach ($lineIds as $lineId) {
                if (! $lines->has($lineId)) {
                    throw new InvalidArgumentException('One of the selected charges is no longer pending for this patient. Refresh and try again.');
                }
            }

            $payments = collect();

            foreach ($lines->groupBy('invoice_id') as $invoiceLines) {
                /** @var Invoice $invoice */
                $invoice = $invoiceLines->first()->invoice;

                if ($invoice->status === InvoiceStatus::Draft) {
                    $invoice = $this->issuanceService->issue($invoice);
                }

                $amount = $invoiceLines->reduce(
                    fn (string $carry, InvoiceLine $line): string => bcadd($carry, $line->remainingAmount(), 2),
                    '0',
                );

                $allocations = $this->allocationBuilder->allocateAmountAcrossUnpaidLines(
                    $invoice,
                    $amount,
                    $invoiceLines->pluck('id')->map(fn ($id): string => (string) $id)->all(),
                );

                $payments->push($this->paymentRecordingService->record(
                    allocations: $allocations,
                    method: $method,
                    gateway: $method->value,
                    currency: $currency,
                    patientId: $patientId,
                    branchId: $branchId,
                    recordedBy: $recordedBy,
                    metadata: $metadata,
                ));
            }

            return $payments;
        });
    }

    /**
     * Total still owed for the given lines, or null when any line is not pending.
     */
    public function remainingFor(array $lineIds, string $patientId): ?string
    {
        $lineIds = array_values(array_unique(array_map('strval', $lineIds)));

        if ($lineIds === []) {
            return '0.00';
        }

        $lines = $this->pendingLinesQuery($patientId, null)->whereIn('id', $lineIds)->get();

        if ($lines->count() !== count($lineIds)) {
            return null;
        }

        return $lines->reduce(fn (string $carry, InvoiceLine $line): string => bcadd($carry, $line->remainingAmount(), 2), '0');
    }

    /**
     * Morph alias of Clinical's RequestItem, resolved lazily because Clinical is an
     * optional peer of Billing. Without Clinical there are no orders and no pending charges.
     */
    protected function requestItemMorphClass(): ?string
    {
        return OptionalClass::when(
            'Modules\\Clinical\\Models\\RequestItem',
            fn (string $class): string => (new $class)->getMorphClass(),
            'Clinical',
        );
    }

    protected function pendingLinesQuery(string $patientId, ?string $branchId): \Illuminate\Database\Eloquent\Builder
    {
        $morphClass = $this->requestItemMorphClass();

        return InvoiceLine::query()
            ->when(
                $morphClass !== null,
                fn ($query) => $query->where('billable_type', $morphClass),
                fn ($query) => $query->whereRaw('1 = 0'),
            )
            ->whereIn('line_status', [InvoiceLineStatus::Unpaid, InvoiceLineStatus::Partial])
            ->whereColumn('amount_paid', '<', 'line_total')
            ->whereHas('invoice', function ($query) use ($patientId, $branchId): void {
                $query->withoutGlobalScopes()
                    ->where('patient_id', $patientId)
                    ->whereIn('status', [InvoiceStatus::Draft, InvoiceStatus::Issued, InvoiceStatus::PartiallyPaid]);

                if ($branchId !== null) {
                    $query->where('branch_id', $branchId);
                }
            });
    }
}
