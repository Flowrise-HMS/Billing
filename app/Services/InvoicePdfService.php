<?php

namespace Modules\Billing\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Billing\Models\Invoice;

class InvoicePdfService
{
    public function render(Invoice $invoice): string
    {
        $invoice->loadMissing([
            'patient',
            'branch',
            'encounter',
            'lines.service',
        ]);

        return Pdf::loadView('billing::pdf.invoice', [
            'invoice' => $invoice,
        ])->setPaper('a4')->output();
    }

    public function filename(Invoice $invoice): string
    {
        return sprintf('invoice-%s.pdf', $invoice->invoice_number);
    }
}
