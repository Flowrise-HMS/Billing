<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Events\InvoiceBecameFullyPaid;
use Modules\Billing\Events\InvoiceBecamePartiallyPaid;
use Modules\Billing\Events\InvoiceTotalsUpdated;
use Modules\Billing\Events\PaymentConfirmed;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PaymentAllocation;

class PaymentRecordingService
{
    public function __construct(
        protected InvoiceTotalsService $totalsService
    ) {}

    /**
     * @param  array<string, string>  $allocations  invoice_line_id => amount (decimal strings)
     */
    public function record(
        array $allocations,
        PaymentMethod $method,
        string $gateway,
        string $currency,
        ?string $patientId,
        string $branchId,
        ?int $recordedBy,
        ?string $providerTransactionId = null,
        array $metadata = [],
        ?PaymentType $type = null
    ): Payment {
        if ($allocations === []) {
            throw new InvalidArgumentException('Payment requires at least one allocation.');
        }

        $providerTransactionId ??= (string) Str::uuid();

        $totalAmount = '0';
        foreach ($allocations as $amount) {
            $totalAmount = bcadd($totalAmount, (string) $amount, 2);
        }

        return DB::transaction(function () use ($allocations, $method, $gateway, $currency, $patientId, $branchId, $recordedBy, $providerTransactionId, $metadata, $totalAmount, $type) {
            $lineIds = array_keys($allocations);
            sort($lineIds);

            $firstLine = InvoiceLine::query()->whereIn('id', $lineIds)->first();
            if (! $firstLine) {
                throw new InvalidArgumentException('One or more invoice lines were not found.');
            }

            $invoiceId = $firstLine->invoice_id;

            Invoice::query()
                ->withoutGlobalScopes()
                ->whereKey($invoiceId)
                ->lockForUpdate()
                ->firstOrFail();

            $lines = InvoiceLine::query()
                ->whereIn('id', $lineIds)
                ->with('invoice')
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            if ($lines->count() !== count($lineIds)) {
                throw new InvalidArgumentException('One or more invoice lines were not found.');
            }

            if ($lines->pluck('invoice_id')->unique()->count() > 1) {
                throw new InvalidArgumentException('A single payment must allocate to lines on one invoice only.');
            }

            foreach ($lines as $line) {
                if ($line->invoice->status === InvoiceStatus::Draft) {
                    throw new InvalidArgumentException('Cannot record payment against a draft invoice.');
                }
                if ($line->invoice->status === InvoiceStatus::Void) {
                    throw new InvalidArgumentException('Cannot record payment against a void invoice.');
                }
            }

            $payment = Payment::query()->create([
                'patient_id' => $patientId,
                'branch_id' => $branchId,
                'method' => $method,
                'gateway' => $gateway,
                'type' => $type ?? PaymentType::Payment,
                'amount' => $totalAmount,
                'currency' => $currency,
                'provider_transaction_id' => $providerTransactionId,
                'received_at' => now(),
                'recorded_by' => $recordedBy,
                'metadata' => $metadata,
            ]);

            foreach ($allocations as $lineId => $amount) {
                $line = $lines->get($lineId);

                if (bccomp((string) $amount, '0', 2) > 0) {
                    $remaining = bcsub((string) $line->line_total, (string) $line->amount_paid, 2);
                    if (bccomp((string) $amount, $remaining, 2) > 0) {
                        throw new InvalidArgumentException("Allocation exceeds remaining for line {$lineId}.");
                    }
                } elseif (bccomp((string) $amount, '0', 2) < 0) {
                    $refundMax = bcsub('0', (string) $line->amount_paid, 2);
                    if (bccomp((string) $amount, $refundMax, 2) < 0) {
                        throw new InvalidArgumentException("Refund exceeds paid amount for line {$lineId}.");
                    }
                }

                PaymentAllocation::query()->create([
                    'payment_id' => $payment->id,
                    'invoice_line_id' => $line->id,
                    'amount' => $amount,
                ]);

                $this->totalsService->syncLineAmountPaidFromAllocations($line->fresh());
            }

            $invoice = $lines->first()->invoice->fresh(['lines']);
            $beforeStatus = $invoice->status;
            $this->totalsService->recalculate($invoice);
            $invoiceAfter = $invoice->fresh(['lines']);

            DB::afterCommit(function () use ($invoiceAfter, $beforeStatus, $payment) {
                Event::dispatch(new InvoiceTotalsUpdated($invoiceAfter));
                Event::dispatch(new PaymentConfirmed($payment->fresh(['allocations']), $invoiceAfter));
                if ($invoiceAfter->status !== $beforeStatus) {
                    if ($invoiceAfter->status === InvoiceStatus::Paid) {
                        Event::dispatch(new InvoiceBecameFullyPaid($invoiceAfter));
                    } elseif ($invoiceAfter->status === InvoiceStatus::PartiallyPaid) {
                        Event::dispatch(new InvoiceBecamePartiallyPaid($invoiceAfter));
                    }
                }
            });

            return $payment->fresh(['allocations']);
        });
    }
}
