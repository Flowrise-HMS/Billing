<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use InvalidArgumentException;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Billing\Events\PaymentCheckoutSessionCreated;
use Modules\Billing\Gateways\PaymentGatewayManager;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\PaymentIntent;

class CheckoutSessionService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected InvoiceIssuanceService $invoiceIssuanceService
    ) {}

    /**
     * Reuse a still-valid pending intent scoped to exactly this line, or mint a fresh
     * one. Stale intents (amount no longer matches what's owed, e.g. after a price
     * correction) are expired rather than reused, so the patient is never redirected
     * to pay a gateway session for the wrong amount.
     */
    public function resolveOrCreateForLine(InvoiceLine $line, string $driver): PaymentIntent
    {
        $invoice = Invoice::query()->withoutGlobalScopes()->findOrFail($line->invoice_id);
        $owed = $line->remainingAmount();

        $existing = PaymentIntent::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', PaymentIntentStatus::Pending)
            ->where('expires_at', '>', now())
            ->get()
            ->first(fn (PaymentIntent $intent) => $intent->line_ids === [$line->id]);

        if ($existing) {
            if (bccomp((string) $existing->amount, $owed, 2) === 0) {
                return $existing;
            }

            $existing->update(['status' => PaymentIntentStatus::Expired]);
        }

        return $this->createForInvoice($invoice, $driver, [], [$line->id]);
    }

    /**
     * @param  string[]|null  $lineIds  Restrict checkout to specific line IDs
     */
    public function createForInvoice(
        Invoice $invoice,
        string $driver,
        array $metadata = [],
        ?array $lineIds = null
    ): PaymentIntent {
        if ($invoice->status === InvoiceStatus::Draft) {
            $invoice = $this->invoiceIssuanceService->issue($invoice);
        }

        if ($lineIds) {
            $amount = $this->calculateLineTotal($invoice, $lineIds);
        } else {
            $amount = $invoice->balanceDue();
        }

        if (bccomp($amount, '0', 2) <= 0) {
            throw new InvalidArgumentException('Invoice has no balance due.');
        }

        $config = BranchPaymentGatewayConfig::query()
            ->where('branch_id', $invoice->branch_id)
            ->where('driver', strtolower($driver))
            ->where('is_enabled', true)
            ->first();

        if (! $config) {
            throw new InvalidArgumentException('Gateway is not configured for this branch.');
        }

        return DB::transaction(function () use ($invoice, $config, $metadata, $amount, $lineIds) {
            $intent = PaymentIntent::query()->create([
                'invoice_id' => $invoice->id,
                'branch_id' => $invoice->branch_id,
                'gateway' => $config->driver,
                'status' => PaymentIntentStatus::Pending,
                'amount' => $amount,
                'currency' => $invoice->currency,
                'line_ids' => $lineIds,
                'client_reference' => (string) Str::uuid(),
                'metadata' => $metadata,
                'expires_at' => now()->addHours(2),
            ]);

            $gateway = $this->gatewayManager->driver($config->driver);
            $intent = $gateway->createCheckout($config, $intent->fresh());

            DB::afterCommit(fn () => Event::dispatch(new PaymentCheckoutSessionCreated($intent)));

            return $intent->fresh();
        });
    }

    /**
     * @param  string[]  $lineIds
     */
    protected function calculateLineTotal(Invoice $invoice, array $lineIds): string
    {
        $lines = InvoiceLine::query()
            ->where('invoice_id', $invoice->id)
            ->whereIn('id', $lineIds)
            ->get();

        $total = '0';
        foreach ($lines as $line) {
            $total = bcadd($total, $line->remainingAmount(), 2);
        }

        return $total;
    }
}
