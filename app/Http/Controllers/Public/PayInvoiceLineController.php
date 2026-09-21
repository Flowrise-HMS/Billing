<?php

namespace Modules\Billing\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Services\CheckoutSessionService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Public, unauthenticated landing page for the durable "pay this order
 * online" link embedded in SMS/email. Gateway-agnostic by construction: it
 * never references a specific driver, only whichever BranchPaymentGatewayConfig
 * is enabled for the invoice's branch.
 */
class PayInvoiceLineController extends Controller
{
    public function __invoke(InvoiceLine $line, CheckoutSessionService $checkout): View|RedirectResponse
    {
        $invoice = Invoice::query()->withoutGlobalScopes()->find($line->invoice_id);

        if (! $invoice) {
            throw new NotFoundHttpException;
        }

        if ($line->line_status === InvoiceLineStatus::Paid) {
            return $this->status(
                __('Already paid'),
                __('This charge has already been paid. Thank you.'),
                'success',
            );
        }

        if ($line->line_status === InvoiceLineStatus::Void || $invoice->status === InvoiceStatus::Void) {
            return $this->status(
                __('Charge cancelled'),
                __('This charge was cancelled and does not need to be paid.'),
                'warning',
            );
        }

        $config = BranchPaymentGatewayConfig::query()
            ->where('branch_id', $invoice->branch_id)
            ->where('is_enabled', true)
            ->first();

        if (! $config) {
            return $this->status(
                __('Online payment not available yet'),
                __('Online payment is not available for this facility yet. Please pay at the billing desk.'),
                'warning',
            );
        }

        try {
            $intent = $checkout->resolveOrCreateForLine($line, $config->driver);
        } catch (\Throwable $e) {
            report($e);

            return $this->status(
                __('Payment temporarily unavailable'),
                __('We could not start your payment right now. Please try again shortly or pay at the billing desk.'),
                'danger',
            );
        }

        if (! $intent->checkout_url) {
            return $this->status(
                __('Payment temporarily unavailable'),
                __('We could not start your payment right now. Please try again shortly or pay at the billing desk.'),
                'danger',
            );
        }

        return redirect()->away($intent->checkout_url);
    }

    protected function status(string $title, string $message, string $tone): View
    {
        return view('billing::public.pay-line-status', compact('title', 'message', 'tone'));
    }
}
