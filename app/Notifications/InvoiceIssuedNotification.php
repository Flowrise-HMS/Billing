<?php

namespace Modules\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Notifications\Concerns\BuildsPatientFacingChannels;
use Modules\Core\Notifications\Concerns\RespectsNotificationSettings;

// todo:: payment gateway yet to be implemented
// (when wired, append a pay-now URL/MoMo prompt to both mail and SMS bodies)
class InvoiceIssuedNotification extends Notification implements ShouldQueue
{
    use BuildsPatientFacingChannels, Queueable, RespectsNotificationSettings;

    public function __construct(protected Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        $channels = $this->channelsFor($notifiable);

        try {
            $settings = app(\Modules\Core\Support\AppSettings::class)->notifications();
            $billing = app(\Modules\Core\Support\AppSettings::class)->billing();

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

        return (new MailMessage)
            ->subject(__('Invoice :number issued', ['number' => $invoice->invoice_number]))
            ->view('billing::emails.invoice-issued', [
                'invoice' => $invoice,
                'balance' => $balance,
                'pdfUrl' => url(route('billing.invoices.pdf', $invoice)),
            ]);
    }

    public function toSms(object $notifiable): string
    {
        $invoice = $this->invoice;

        return __('Invoice :number is ready. Sign in to the patient portal to view balance and payment options.', [
            'number' => $invoice->invoice_number,
        ]);
    }
}
