<?php

namespace Modules\Billing\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Models\Payment;
use Modules\Core\Models\Branch;
use Modules\Core\Support\ModuleAvailability;

/**
 * Monthly revenue summary using the shared revenue predicate (type=Payment only).
 */
class MonthlyRevenueService
{
    /**
     * @return array<string, mixed>
     */
    public function monthly(Branch $branch, CarbonInterface $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $payments = Payment::query()
            ->where('branch_id', $branch->id)
            ->whereBetween('received_at', [$start, $end])
            ->with('allocations.invoiceLine')
            ->get();

        $revenue = $payments->where('type', PaymentType::Payment);
        $refunds = $payments->where('type', PaymentType::Refund);

        $revenueTotal = $this->sum($revenue);
        $refundsTotal = $this->sum($refunds);
        $byMethod = [];
        foreach ($revenue as $payment) {
            $key = $payment->method->value;
            $byMethod[$key] = bcadd($byMethod[$key] ?? '0', (string) $payment->amount, 2);
        }

        $byCategory = [];
        foreach ($revenue as $payment) {
            foreach ($payment->allocations as $allocation) {
                $line = $allocation->invoiceLine;
                if ($line === null) {
                    continue;
                }
                $category = (string) ($line->service_id ?? 'uncategorized');
                $byCategory[$category] = bcadd($byCategory[$category] ?? '0', (string) $allocation->amount, 2);
            }
        }

        $result = [
            'revenue_total' => $revenueTotal,
            'refunds_total' => bcsub('0', $refundsTotal, 2),
            'net_revenue' => bcsub($revenueTotal, bcsub('0', $refundsTotal, 2), 2),
            'revenue_by_method' => $byMethod,
            'revenue_by_category' => $byCategory,
        ];

        if (ModuleAvailability::insuranceEnabled()) {
            $result['insurance_split'] = $this->insuranceSplit($branch, $start, $end);
        }

        return $result;
    }

    /**
     * Reuses the invoice-line insurance split logic (mirrors RevenueReportService's
     * patient_responsibility vs insurance_expected derivation).
     *
     * @return array<string, string>
     */
    private function insuranceSplit(Branch $branch, CarbonInterface $start, CarbonInterface $end): array
    {
        $totals = DB::table('invoice_lines')
            ->join('invoices', 'invoices.id', '=', 'invoice_lines.invoice_id')
            ->where('invoices.branch_id', $branch->id)
            ->whereIn('invoices.status', ['issued', 'partially_paid', 'paid'])
            ->whereBetween(DB::raw('COALESCE(invoices.issued_at, invoices.created_at)'), [$start, $end])
            ->selectRaw('SUM(invoice_lines.insurance_expected_amount) as insurer_total, SUM(invoice_lines.patient_responsibility_amount) as patient_total')
            ->first();

        return [
            'insurer_amount' => $this->money((string) ($totals->insurer_total ?? 0)),
            'patient_amount' => $this->money((string) ($totals->patient_total ?? 0)),
        ];
    }

    private function money(string $value): string
    {
        return bcadd($value, '0', 2);
    }

    private function sum($rows): string
    {
        $total = '0';
        foreach ($rows as $payment) {
            $total = bcadd($total, (string) $payment->amount, 2);
        }

        return $total;
    }
}
