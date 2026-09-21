<?php

namespace Modules\Billing\Notifications\Concerns;

use Illuminate\Support\Facades\URL;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Models\InvoiceLine;

trait BuildsLinePaymentUrl
{
    /**
     * Durable, signed link to the public pay-this-line page. Never mints a
     * PaymentIntent or calls a gateway here — that only happens when the
     * patient actually clicks, so sending this notification never depends
     * on a live gateway HTTP call.
     */
    protected function linePaymentUrl(InvoiceLine $line): ?string
    {
        if ($line->line_status === InvoiceLineStatus::Void) {
            return null;
        }

        if (bccomp($line->remainingAmount(), '0', 2) <= 0) {
            return null;
        }

        $ttlDays = (int) config('billing.public_pay_link.ttl_days', 7);

        return URL::temporarySignedRoute(
            'billing.public.pay-line',
            now()->addDays($ttlDays),
            ['line' => $line->id],
        );
    }
}
