<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\InvoicePdfService;

class InvoicePdfController extends BillingController
{
    public function __invoke(Request $request, Invoice $invoice, InvoicePdfService $pdfs): Response
    {
        $user = $request->user();
        $download = $request->boolean('download');

        if ($download) {
            abort_unless($user?->can('download_invoice'), 403);
        } else {
            abort_unless(
                $user?->can('view_invoice_pdf') || $user?->can('print_invoice'),
                403
            );
        }

        $pdf = $pdfs->render($invoice);
        $disposition = $download ? 'attachment' : 'inline';
        $filename = $pdfs->filename($invoice);

        return response($pdf, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => $disposition.'; filename="'.$filename.'"',
        ]);
    }
}
