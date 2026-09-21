<?php

namespace Modules\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Notifications\Concerns\ResolvesInvoiceCheckoutUrl;
use Modules\Core\Notifications\Concerns\ResolvesNotificationChannels;

class InvoiceLineAddedNotification extends Notification implements ShouldQueue
{
    use Queueable, ResolvesInvoiceCheckoutUrl, ResolvesNotificationChannels;

    public function __construct(protected InvoiceLine $line) {}

    public function via(object $notifiable): array
    {
        return $this->settingsChannels($notifiable, 'invoice_line_added_mail', 'invoice_line_added_sms');
    }

    public function toMail(object $notifiable): MailMessage
    {
        $line = $this->line->loadMissing(['invoice.patient', 'service']);
        $invoice = $line->invoice;

        $serviceName = $line->service?->name ?? $line->description ?? __('Service');

        $checkoutUrl = $this->resolveCheckoutUrl($invoice);

        return (new MailMessage)
            ->subject(__('Invoice :number updated', ['number' => $invoice->invoice_number]))
            ->view('billing::emails.invoice-line-added', [
                'line' => $line,
                'invoice' => $invoice,
                'serviceName' => $serviceName,
                'pdfUrl' => url(route('billing.invoices.pdf', $invoice)),
                'checkoutUrl' => $checkoutUrl,
            ]);
    }

    public function toSms(object $notifiable): string
    {
        $line = $this->line->loadMissing(['invoice']);
        $invoice = $line->invoice;
        $checkoutUrl = $this->resolveCheckoutUrl($invoice);

        $message = __('Billing update: invoice :number changed.', ['number' => $invoice->invoice_number]);

        if ($checkoutUrl) {
            $message .= ' '.__('Pay now: :url', ['url' => $checkoutUrl]);
        }

        return $message;
    }
}
