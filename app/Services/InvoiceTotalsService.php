<?php

namespace Modules\Billing\Services;

use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Exceptions\InvoiceConcurrentModificationException;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;

class InvoiceTotalsService
{
    public function recalculate(Invoice $invoice): Invoice
    {
        $invoice = $invoice->fresh();

        $lines = $invoice->lines()->get();
        $subtotal = '0';
        $discount = '0';
        $tax = '0';
        $paid = '0';

        foreach ($lines as $line) {
            if ($line->line_status === InvoiceLineStatus::Void) {
                continue;
            }
            $lineSubtotal = bcmul((string) $line->unit_price, (string) $line->quantity, 2);
            $subtotal = bcadd($subtotal, $lineSubtotal, 2);
            $discount = bcadd($discount, (string) $line->discount_amount, 2);
            $tax = bcadd($tax, (string) $line->tax_amount, 2);
            $paid = bcadd($paid, (string) $line->amount_paid, 2);
        }

        $total = bcadd(bcsub(bcadd($subtotal, $tax, 2), $discount, 2), '0', 2);

        $expectedLock = (int) $invoice->lock_version;
        $newLockVersion = $expectedLock + 1;

        if ($invoice->status === InvoiceStatus::Draft || $invoice->status === InvoiceStatus::Void) {
            $newStatus = $invoice->status;
        } elseif (bccomp($total, '0', 2) > 0 && bccomp($paid, $total, 2) >= 0) {
            $newStatus = InvoiceStatus::Paid;
        } elseif (bccomp($paid, '0', 2) > 0) {
            $newStatus = InvoiceStatus::PartiallyPaid;
        } else {
            $newStatus = InvoiceStatus::Issued;
        }

        $affected = Invoice::query()
            ->withoutGlobalScopes()
            ->whereKey($invoice->id)
            ->where('lock_version', $expectedLock)
            ->update([
                'subtotal' => $subtotal,
                'discount_total' => $discount,
                'tax_total' => $tax,
                'total' => $total,
                'amount_paid' => $paid,
                'lock_version' => $newLockVersion,
                'status' => $newStatus->value,
            ]);

        if ($affected === 0) {
            throw new InvoiceConcurrentModificationException(
                'Invoice was modified by another process. Refresh and try again.'
            );
        }

        return Invoice::query()->withoutGlobalScopes()->with('lines')->findOrFail($invoice->id);
    }

    public function syncLineAmountPaidFromAllocations(InvoiceLine $line): void
    {
        $sum = $line->paymentAllocations()->sum('amount');
        $line->amount_paid = (string) $sum;
        $rem = bcsub((string) $line->line_total, (string) $line->amount_paid, 2);
        if (bccomp($rem, '0', 2) <= 0) {
            $line->line_status = InvoiceLineStatus::Paid;
        } elseif (bccomp((string) $line->amount_paid, '0', 2) > 0) {
            $line->line_status = InvoiceLineStatus::Partial;
        } else {
            $line->line_status = $line->line_status === InvoiceLineStatus::Void
                ? InvoiceLineStatus::Void
                : InvoiceLineStatus::Unpaid;
        }
        $line->save();
    }
}
