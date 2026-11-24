<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentPlanStatus;
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

    /**
     * Void an issued, unpaid invoice and cancel its active payment plan.
     */
    public function void(Invoice $invoice): Invoice
    {
        if (in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Void, InvoiceStatus::Paid, InvoiceStatus::PartiallyPaid], true)) {
            throw new \InvalidArgumentException('Only issued, unpaid invoices can be voided.');
        }

        return DB::transaction(function () use ($invoice): Invoice {
            $invoice->update(['status' => InvoiceStatus::Void]);

            $activePlan = $invoice->paymentPlan()->where('status', PaymentPlanStatus::Active)->first();

            if ($activePlan !== null) {
                app(PaymentPlanService::class)->cancelPlan($activePlan);
            }

            return $invoice->fresh(['lines']);
        });
    }
}
