<?php

namespace Modules\Billing\Mail;

use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Modules\Billing\Models\Invoice;

class InvoiceUnpaidMail extends Mailable
{
    use SerializesModels;

    public function __construct(
        public Invoice $invoice,
        public ?string $checkoutUrl = null,
    ) {}

    public function build(): self
    {
        return $this->subject(__('Payment reminder: invoice :number', ['number' => $this->invoice->invoice_number]))
            ->view('billing::emails.invoice-unpaid', [
                'invoice' => $this->invoice,
                'balance' => $this->invoice->balanceDue(),
                'pdfUrl' => url(route('billing.invoices.pdf', $this->invoice)),
                'checkoutUrl' => $this->checkoutUrl,
            ]);
    }
}
