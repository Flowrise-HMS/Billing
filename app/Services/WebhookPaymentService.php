<?php

namespace Modules\Billing\Services;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Gateways\PaymentGatewayManager;
use Modules\Billing\Models\BillingWebhookEvent;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentIntent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class WebhookPaymentService
{
    public function __construct(
        protected PaymentGatewayManager $gatewayManager,
        protected PaymentRecordingService $paymentRecordingService,
        protected InvoiceAllocationBuilder $allocationBuilder
    ) {}

    public function process(Request $request, string $driver, string $branchId): void
    {
        $config = BranchPaymentGatewayConfig::query()
            ->where('branch_id', $branchId)
            ->where('driver', strtolower($driver))
            ->where('is_enabled', true)
            ->firstOrFail();

        $gateway = $this->gatewayManager->driver($config->driver);

        if (! $gateway->verifyWebhookSignature($request, $config)) {
            throw new AccessDeniedHttpException('Invalid webhook signature.');
        }

        $parsed = $gateway->parseWebhookPayload($request);
        if (! $parsed || ! $parsed['success'] || $parsed['reference'] === '') {
            return;
        }

        $idempotencyKey = strtolower($driver).':'.$parsed['reference'];

        if (BillingWebhookEvent::query()->where('idempotency_key', $idempotencyKey)->exists()) {
            return;
        }

        $intent = PaymentIntent::query()
            ->where('client_reference', $parsed['reference'])
            ->where('branch_id', $branchId)
            ->where('gateway', $config->driver)
            ->first();

        if (! $intent) {
            throw new NotFoundHttpException('Payment intent not found.');
        }

        $invoice = Invoice::query()->withoutGlobalScopes()->with('lines')->findOrFail($intent->invoice_id);

        DB::transaction(function () use ($invoice, $intent, $parsed, $idempotencyKey, $config) {
            $amountMajor = isset($parsed['amount_minor'])
                ? bcdiv((string) $parsed['amount_minor'], '100', 2)
                : (string) $intent->amount;

            $due = $invoice->balanceDue();
            if (bccomp($amountMajor, $due, 2) > 0) {
                $amountMajor = $due;
            }

            $allocations = $this->allocationBuilder->allocateAmountAcrossUnpaidLines($invoice, $amountMajor);

            if ($allocations === []) {
                return;
            }

            $payment = $this->paymentRecordingService->record(
                allocations: $allocations,
                method: PaymentMethod::Gateway,
                gateway: $config->driver,
                currency: $invoice->currency,
                patientId: $invoice->patient_id,
                branchId: (string) $invoice->branch_id,
                recordedBy: null,
                providerTransactionId: $parsed['reference'],
                metadata: ['source' => 'webhook', 'driver' => $config->driver],
            );

            BillingWebhookEvent::query()->create([
                'driver' => $config->driver,
                'idempotency_key' => $idempotencyKey,
                'payment_id' => $payment->id,
                'processed_at' => now(),
                'metadata' => ['reference' => $parsed['reference']],
            ]);

            $intent->update(['status' => PaymentIntentStatus::Succeeded]);
        });
    }
}
