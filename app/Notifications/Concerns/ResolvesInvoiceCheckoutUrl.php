<?php

namespace Modules\Billing\Notifications\Concerns;

use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentIntent;
use Modules\Billing\Services\CheckoutSessionService;

trait ResolvesInvoiceCheckoutUrl
{
    protected function resolveCheckoutUrl(Invoice $invoice): ?string
    {
        if (bccomp($invoice->balanceDue(), '0', 2) <= 0) {
            return null;
        }

        $existing = PaymentIntent::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', PaymentIntentStatus::Pending)
            ->where('expires_at', '>', now())
            ->first();

        if ($existing?->checkout_url) {
            return $existing->checkout_url;
        }

        try {
            $config = BranchPaymentGatewayConfig::query()
                ->where('branch_id', $invoice->branch_id)
                ->where('is_enabled', true)
                ->first();

            if (! $config) {
                return null;
            }

            $intent = app(CheckoutSessionService::class)
                ->createForInvoice($invoice, $config->driver);

            return $intent->checkout_url;
        } catch (\Throwable) {
            return null;
        }
    }
}
