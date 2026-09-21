<?php

namespace Modules\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Modules\Billing\Mail\InvoiceUnpaidMail;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Notifications\Concerns\ResolvesInvoiceCheckoutUrl;
use Modules\Core\Notifications\Concerns\ResolvesNotificationChannels;

class InvoiceUnpaidNotification extends Notification implements ShouldQueue
{
    use Queueable, ResolvesInvoiceCheckoutUrl, ResolvesNotificationChannels;

    public function __construct(protected Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return $this->settingsChannels($notifiable, 'invoice_unpaid_mail', 'invoice_unpaid_sms');
    }

    public function toMail(object $notifiable): InvoiceUnpaidMail
    {
        $invoice = $this->invoice->loadMissing(['patient', 'branch']);
        $checkoutUrl = $this->resolveCheckoutUrl($invoice);

        $address = method_exists($notifiable, 'routeNotificationForMail')
            ? $notifiable->routeNotificationForMail($this)
            : ($notifiable->email ?? null);

        return (new InvoiceUnpaidMail($invoice, $checkoutUrl))
            ->to($address);
    }

    public function toSms(object $notifiable): string
    {
        $invoice = $this->invoice;
        $checkoutUrl = $this->resolveCheckoutUrl($invoice);

        $message = __('Reminder: invoice :number has an outstanding balance of :amount :currency.', [
            'number' => $invoice->invoice_number,
            'amount' => $invoice->balanceDue(),
            'currency' => $invoice->currency,
        ]);

        if ($checkoutUrl) {
            $message .= ' '.__('Pay now: :url', ['url' => $checkoutUrl]);
        }

        return $message;
    }
}
