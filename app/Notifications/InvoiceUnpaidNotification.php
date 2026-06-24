<?php

namespace Modules\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Modules\Billing\Mail\InvoiceUnpaidMail;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Notifications\Concerns\BuildsPatientFacingChannels;
use Modules\Billing\Notifications\Concerns\ResolvesInvoiceCheckoutUrl;
use Modules\Core\Notifications\Concerns\RespectsNotificationSettings;
use Modules\Core\Support\AppSettings;

class InvoiceUnpaidNotification extends Notification implements ShouldQueue
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
                $settings->invoice_unpaid_mail,
                $settings->invoice_unpaid_sms,
                $billing->sms_enabled,
            );
        } catch (\Throwable) {
            return $channels;
        }
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
