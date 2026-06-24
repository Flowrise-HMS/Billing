<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Events\InvoiceIssued;
use Modules\Billing\Events\InvoiceTotalsUpdated;
use Modules\Billing\Models\Invoice;

class InvoiceIssuanceService
{
    public function __construct(
        protected InvoiceTotalsService $totalsService
    ) {}

    public function issue(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Draft) {
            throw new \InvalidArgumentException('Only draft invoices can be issued.');
        }

        return DB::transaction(function () use ($invoice) {
            $invoice->status = InvoiceStatus::Issued;
            $invoice->issued_at = now();
            $invoice->save();

            $invoice = $this->totalsService->recalculate($invoice->fresh(['lines']));

            DB::afterCommit(function () use ($invoice) {
                $invoice = $invoice->fresh(['lines']);
                Event::dispatch(new InvoiceIssued($invoice));
                Event::dispatch(new InvoiceTotalsUpdated($invoice));
            });

            return $invoice->fresh(['lines']);
        });
    }
}
