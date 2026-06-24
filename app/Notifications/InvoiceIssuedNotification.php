<?php

namespace Modules\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Notifications\Concerns\BuildsPatientFacingChannels;
use Modules\Billing\Notifications\Concerns\ResolvesInvoiceCheckoutUrl;
use Modules\Core\Notifications\Concerns\RespectsNotificationSettings;
use Modules\Core\Support\AppSettings;

class InvoiceIssuedNotification extends Notification implements ShouldQueue
{
    use BuildsPatientFacingChannels, Queueable, ResolvesInvoiceCheckoutUrl, RespectsNotificationSettings;

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
            $message .= ' '.__('Pay now: :url', ['url' => $checkoutUrl]);
        }

        return $message;
    }
}
