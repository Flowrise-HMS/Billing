<?php

namespace Modules\Billing\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Notifications\Concerns\BuildsPatientFacingChannels;

// todo:: payment gateway yet to be implemented
// (when wired, append a pay-now URL/MoMo prompt to both mail and SMS bodies)
class InvoiceLineAddedNotification extends Notification implements ShouldQueue
{
    use BuildsPatientFacingChannels, Queueable;

    public function __construct(protected InvoiceLine $line) {}

    public function via(object $notifiable): array
    {
        return $this->channelsFor($notifiable);
    }

    public function toMail(object $notifiable): MailMessage
    {
        $line = $this->line->loadMissing(['invoice.patient', 'service']);
        $invoice = $line->invoice;

        $serviceName = $line->service?->name ?? $line->description ?? __('Service');

        return (new MailMessage)
            ->subject(__('Invoice :number updated', ['number' => $invoice->invoice_number]))
            ->view('billing::emails.invoice-line-added', [
                'line' => $line,
                'invoice' => $invoice,
                'serviceName' => $serviceName,
                'pdfUrl' => url(route('billing.invoices.pdf', $invoice)),
            ]);
    }

    public function toSms(object $notifiable): string
    {
        $line = $this->line->loadMissing(['invoice']);
        $invoice = $line->invoice;

        return __(
            'Billing update: invoice :number changed. Sign in to the patient portal to view details.',
            [
                'number' => $invoice->invoice_number,
            ]
        );
    }
}
