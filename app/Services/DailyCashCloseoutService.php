<?php

namespace Modules\Billing\Services;

use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Models\Payment;
use Modules\Core\Models\Branch;

/**
 * Computes the daily cash closeout figures for one cashier/branch/date.
 *
 * Read-only aggregation over the payments table. Never writes.
 * Refund Payment rows carry a NEGATIVE amount (PaymentRecordingService sums
 * the negative allocations), so refund sums are negative here.
 */
class DailyCashCloseoutService
{
    /**
     * @return array{
     *   revenue_by_method: array<string, string>,
     *   tax_collected: string,
     *   refunds_total: string,
     *   net_revenue: string,
     *   deposits_received: string,
     *   cash_in: string,
     *   cash_refunds: string,
     *   change_given: string,
     *   expected_closing: string,
     *   non_ghs_currencies: list<string>,
     * }
     */
    public function compute(Branch $branch, User $cashier, CarbonInterface $date): array
    {
        $allRows = Payment::query()
            ->where('branch_id', $branch->id)
            ->where('recorded_by', $cashier->id)
            ->whereBetween('received_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->with(['allocations.invoiceLine'])
            ->get();

        $rows = $allRows->where('currency', 'GHS');

        $revenue = $rows->where('type', PaymentType::Payment);
        $deposits = $rows->where('type', PaymentType::Deposit);
        $refunds = $rows->where('type', PaymentType::Refund);

        $revenueByMethod = $this->sumByMethod($revenue);
        $revenueTotal = $this->sumCollection($revenue);
        $refundsTotal = $this->sumCollection($refunds);
        $netRevenue = bcsub($revenueTotal, bcsub('0', $refundsTotal, 2), 2);
        $depositsReceived = $this->sumCollection($deposits);
        $cashIn = bcadd($revenueByMethod[PaymentMethod::Cash->value] ?? '0', $this->sumCollection($deposits->where('method', PaymentMethod::Cash)), 2);
        $cashRefunds = $this->cashRefunds($refunds);
        $changeGiven = $this->changeGiven($revenue);

        return [
            'revenue_by_method' => $revenueByMethod,
            'tax_collected' => $this->taxCollected($revenue),
            'refunds_total' => $refundsTotal,
            'net_revenue' => $netRevenue,
            'deposits_received' => $depositsReceived,
            'cash_in' => $cashIn,
            'cash_refunds' => $cashRefunds,
            'change_given' => $changeGiven,
            'expected_closing' => bcadd($cashIn, bcsub(bcsub('0', $cashRefunds, 2), $changeGiven, 2), 2),
            'non_ghs_currencies' => $this->nonGhsCurrencies($allRows),
        ];
    }

    /**
     * Cash-affecting rows recorded AFTER a summary was finalized for the same
     * date/cashier/branch — these were NOT included in the finalized totals and
     * must be surfaced to the cashier as a warning (spec: stale flag).
     *
     * "Cash-affecting" = the row is method=Cash (any type) OR a refund
     * (gateway refunds are paid back in cash).
     *
     * @return Collection<int, Payment>
     */
    public function postFinalizeTransactions(Branch $branch, User $cashier, CarbonInterface $date, CarbonInterface $finalizedAt): Collection
    {
        return Payment::query()
            ->where('branch_id', $branch->id)
            ->where('recorded_by', $cashier->id)
            ->whereBetween('received_at', [$date->copy()->startOfDay(), $date->copy()->endOfDay()])
            ->where('created_at', '>', $finalizedAt)
            ->get()
            ->filter(fn (Payment $payment) => $payment->method === PaymentMethod::Cash || $payment->type === PaymentType::Refund);
    }

    private function cashRefunds(Collection $refunds): string
    {
        $cashRefundIds = $refunds
            ->pluck('metadata.original_payment_id')
            ->filter()
            ->unique()
            ->values();

        $originalMethods = Payment::query()
            ->whereIn('id', $cashRefundIds)
            ->pluck('method', 'id');

        $total = '0';
        foreach ($refunds as $refund) {
            $originalId = $refund->metadata['original_payment_id'] ?? null;
            if ($originalId && $originalMethods[$originalId] === PaymentMethod::Cash) {
                $total = bcsub('0', bcadd($total, (string) $refund->amount, 2), 2);
            }
        }

        return $total;
    }

    private function changeGiven(Collection $revenue): string
    {
        $total = '0';
        foreach ($revenue as $payment) {
            if ($payment->method !== PaymentMethod::Cash) {
                continue;
            }
            $change = $payment->metadata['change_due'] ?? '0';
            $total = bcadd($total, (string) $change, 2);
        }

        return $total;
    }

    private function taxCollected(Collection $revenue): string
    {
        $total = '0';
        foreach ($revenue as $payment) {
            $total = bcadd($total, $this->paymentTax($payment), 2);
        }

        return $total;
    }

    private function paymentTax(Payment $payment): string
    {
        $tax = '0';
        foreach ($payment->allocations as $allocation) {
            $line = $allocation->invoiceLine;
            if ($line === null) {
                continue;
            }
            $taxableBase = bcsub((string) $line->line_total, (string) $line->tax_amount, 2);
            if (bccomp($taxableBase, '0', 2) <= 0) {
                continue;
            }
            $share = bcmul((string) $allocation->amount, bcdiv((string) $line->tax_amount, $taxableBase, 10), 2);
            $tax = bcadd($tax, $share, 2);
        }

        return $tax;
    }

    /**
     * @param  Collection<int, Payment>  $rows
     * @return array<string, string>
     */
    private function sumByMethod(Collection $rows): array
    {
        $sums = [];
        foreach ($rows as $payment) {
            $key = $payment->method->value;
            $sums[$key] = bcadd($sums[$key] ?? '0', (string) $payment->amount, 2);
        }

        return $sums;
    }

    /**
     * @param  Collection<int, Payment>  $rows
     */
    private function sumCollection(Collection $rows): string
    {
        $total = '0';
        foreach ($rows as $payment) {
            $total = bcadd($total, (string) $payment->amount, 2);
        }

        return $total;
    }

    /**
     * @param  Collection<int, Payment>  $rows
     * @return list<string>
     */
    private function nonGhsCurrencies(Collection $rows): array
    {
        return $rows
            ->pluck('currency')
            ->filter(fn (?string $currency) => $currency !== null && $currency !== 'GHS')
            ->unique()
            ->values()
            ->all();
    }
}
