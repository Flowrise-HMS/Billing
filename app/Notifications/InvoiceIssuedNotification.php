<?php

namespace Modules\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentIntent;
use Modules\Billing\Notifications\Concerns\BuildsPatientFacingChannels;
use Modules\Billing\Services\CheckoutSessionService;
use Modules\Core\Notifications\Concerns\RespectsNotificationSettings;
use Modules\Core\Support\AppSettings;

class InvoiceIssuedNotification extends Notification implements ShouldQueue
{
    use BuildsPatientFacingChannels, Queueable, RespectsNotificationSettings;

    public function __construct(protected Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        $channels = $this->channelsFor($notifiable);

        try {
            $settings = app(AppSettings::class)->notifications();
            $billing = app(AppSettings::class)->billing();

            return $this->applyNotificationSettings(
                $channels,
                $settings->invoice_issued_mail,
                $settings->invoice_issued_sms,
                $billing->sms_enabled,
            );
        } catch (\Throwable) {
            return $channels;
        }
    }

    public function toMail(object $notifiable): MailMessage
    {
        $invoice = $this->invoice->loadMissing(['patient', 'branch']);

        $balance = $invoice->balanceDue();

        $checkoutUrl = $this->resolveCheckoutUrl($invoice);

        return (new MailMessage)
            ->subject(__('Invoice :number issued', ['number' => $invoice->invoice_number]))
            ->view('billing::emails.invoice-issued', [
                'invoice' => $invoice,
                'balance' => $balance,
                'pdfUrl' => url(route('billing.invoices.pdf', $invoice)),
                'checkoutUrl' => $checkoutUrl,
            ]);
    }

    public function toSms(object $notifiable): string
    {
        $invoice = $this->invoice;
        $checkoutUrl = $this->resolveCheckoutUrl($invoice);

        $message = __('Invoice :number is ready.', ['number' => $invoice->invoice_number]);

        if ($checkoutUrl) {
            $message .= ' '. __('Pay now: :url', ['url' => $checkoutUrl]);
        }

        return $message;
    }

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
