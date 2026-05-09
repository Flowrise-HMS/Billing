<?php

namespace Modules\Billing\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Notifications\Concerns\BuildsPatientFacingChannels;

// todo:: payment gateway yet to be implemented
// (when wired, append a pay-now URL/MoMo prompt to both mail and SMS bodies)
class InvoiceIssuedNotification extends Notification
{
    use BuildsPatientFacingChannels;

    public function __construct(protected Invoice $invoice) {}

    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable);
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

        return __('Invoice :number issued. Amount due: :amount :currency. Pay at our front desk.', [
            'number' => $invoice->invoice_number,
            'amount' => $invoice->balanceDue(),
            'currency' => $invoice->currency,
        ]);
    }
}
