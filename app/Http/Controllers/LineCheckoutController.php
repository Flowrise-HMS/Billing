<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Services\CheckoutSessionService;

class LineCheckoutController extends BillingController
{
    public function __invoke(
        Request $request,
        Invoice $invoice,
        CheckoutSessionService $checkout
    ): RedirectResponse {
        $validated = $request->validate([
            'lines' => ['required', 'string'],
        ]);

        $lineIds = explode(',', $validated['lines']);

        $existingIds = InvoiceLine::query()
            ->where('invoice_id', $invoice->id)
            ->whereIn('id', $lineIds)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->toArray();

        if ($existingIds === []) {
            abort(404, 'No valid invoice lines found.');
        }

        $gateway = BranchPaymentGatewayConfig::query()
            ->where('branch_id', $invoice->branch_id)
            ->where('is_enabled', true)
            ->first();

        if (! $gateway) {
            abort(422, 'No payment gateway is configured for this branch.');
        }

        $intent = $checkout->createForInvoice(
            invoice: $invoice,
            driver: $gateway->driver,
            lineIds: $existingIds,
        );

        return redirect()->away($intent->checkout_url);
    }
}
