<?php

namespace Modules\Billing\Services;

use Barryvdh\DomPDF\Facade\Pdf;
use Modules\Billing\Models\Payment;

class PaymentReceiptPdfService
{
    public function render(Payment $payment): string
    {
        $payment->loadMissing([
            'patient',
            'branch',
            'allocations.invoiceLine.invoice',
        ]);

        return Pdf::loadView('billing::pdf.receipt', [
            'payment' => $payment,
            'allocations' => $payment->allocations,
        ])->setPaper('a4')->output();
    }

    public function filename(Payment $payment): string
    {
        return sprintf('receipt-%s.pdf', $payment->id);
    }
}
