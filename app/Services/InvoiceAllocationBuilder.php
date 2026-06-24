<?php

namespace Modules\Billing\Services;

use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Models\Invoice;

class InvoiceAllocationBuilder
{
    /**
     * Build sequential allocations up to $amount (major currency units) across unpaid lines.
     *
     * @param  string[]|null  $onlyLineIds  If set, restrict to these line IDs only
     * @return array<string, string> line_id => amount
     */
    public function allocateAmountAcrossUnpaidLines(Invoice $invoice, string $amount, ?array $onlyLineIds = null): array
    {
        $allocations = [];
        $remaining = $amount;

        $lines = $invoice->lines()->orderBy('id');
        if ($onlyLineIds !== null) {
            $lines->whereIn('id', $onlyLineIds);
        }

        foreach ($lines->get() as $line) {
            if ($line->line_status === InvoiceLineStatus::Void) {
                continue;
            }
            $due = bcsub((string) $line->line_total, (string) $line->amount_paid, 2);
            if (bccomp($due, '0', 2) <= 0) {
                continue;
            }
            if (bccomp($remaining, '0', 2) <= 0) {
                break;
            }
            $part = bccomp($remaining, $due, 2) >= 0 ? $due : $remaining;
            $allocations[(string) $line->id] = $part;
            $remaining = bcsub($remaining, $part, 2);
        }

        return $allocations;
    }
}
