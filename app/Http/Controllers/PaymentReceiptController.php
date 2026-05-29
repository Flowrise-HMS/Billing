<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\PaymentReceiptPdfService;

class PaymentReceiptController extends BillingController
{
    public function __invoke(Request $request, Payment $payment, PaymentReceiptPdfService $receipts): Response
    {
        $user = $request->user();
        $download = $request->boolean('download');

        if ($download) {
            abort_unless($user?->can('download_receipt'), 403);
        } else {
            abort_unless($user?->can('print_receipt'), 403);
        }

        $pdf = $receipts->render($payment, $request->query('line_id'));
        $disposition = $download ? 'attachment' : 'inline';

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$receipts->filename($payment).'"',
        ]);
    }
}
