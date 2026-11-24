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

    /**
     * Record a refund of (part of) a payment as a negative payment allocated
     * back across the original payment's invoice lines, pro rata.
     */
    public function refund(Payment $payment, string $amount, ?string $reason = null, ?int $recordedBy = null, string $source = 'filament'): Payment
    {
        return DB::transaction(function () use ($payment, $amount, $reason, $recordedBy, $source): Payment {
            // Serialise refunds of one payment so concurrent partial refunds cannot exceed it.
            Payment::query()->withoutGlobalScopes()->whereKey($payment->getKey())->lockForUpdate()->firstOrFail();

            return $this->recordRefund($payment, $amount, $reason, $recordedBy, $source);
        });
    }

    /**
     * What is left to refund on a payment after earlier refunds of it.
     */
    public function refundableAmount(Payment $payment): string
    {
        $refunded = Payment::query()
            ->withoutGlobalScopes()
            ->where('type', PaymentType::Refund)
            ->where('metadata->original_payment_id', $payment->getKey())
            ->pluck('amount')
            ->reduce(fn (string $sum, mixed $value): string => bcadd($sum, ltrim((string) $value, '-'), 2), '0');

        return bcsub((string) $payment->amount, $refunded, 2);
    }

    protected function recordRefund(Payment $payment, string $amount, ?string $reason, ?int $recordedBy, string $source): Payment
    {
        $payment->loadMissing('allocations');

        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException('The refund amount must be greater than zero.');
        }

        if (in_array($payment->type, [PaymentType::Refund, PaymentType::WriteOff], true)) {
            throw new InvalidArgumentException('Refunds and write-offs cannot be refunded.');
        }

        if (bccomp($amount, $this->refundableAmount($payment), 2) > 0) {
            throw new InvalidArgumentException('The refund exceeds the payment amount still refundable.');
        }

        $totalAllocated = '0';
        foreach ($payment->allocations as $allocation) {
            $totalAllocated = bcadd($totalAllocated, (string) $allocation->amount, 2);
        }

        if (bccomp($totalAllocated, '0', 2) <= 0) {
            throw new InvalidArgumentException('This payment has no allocations to refund.');
        }

        $allocations = [];
        foreach ($payment->allocations as $allocation) {
            $ratio = bcdiv((string) $allocation->amount, $totalAllocated, 10);
            $allocations[(string) $allocation->invoice_line_id] = bcsub('0', bcmul($ratio, $amount, 2), 2);
        }

        return $this->record(
            allocations: $allocations,
            method: PaymentMethod::Gateway,
            gateway: $payment->gateway,
            currency: $payment->currency,
            patientId: $payment->patient_id,
            branchId: (string) $payment->branch_id,
            recordedBy: $recordedBy,
            metadata: [
                'source' => $source,
                'action' => 'refund',
                'original_payment_id' => $payment->id,
                'reason' => $reason ?? '',
            ],
            type: PaymentType::Refund,
        );
    }
}
