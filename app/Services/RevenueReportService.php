<?php

namespace Modules\Billing\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Data\BillingReportCriteria;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\Concerns\NormalizesMoney;
use Modules\Core\Models\Branch;
use Modules\Core\Support\ClientIdentity;

class RevenueReportService
{
    use NormalizesMoney;

    /**
     * @return array<string, mixed>
     */
    public function build(CarbonInterface $startDate, CarbonInterface $endDate, ?string $branchId = null): array
    {
        return $this->buildFromCriteria(new BillingReportCriteria(
            startDate: $startDate,
            endDate: $endDate,
            branchId: $branchId,
        ));
    }

    /**
     * @return array<string, mixed>
     */
    public function buildFromCriteria(BillingReportCriteria $criteria): array
    {
        $start = $criteria->startDate->copy()->startOfDay();
        $end = $criteria->endDate->copy()->endOfDay();
        $branchId = $criteria->branchId;

        $billed = $this->normalizeMoneyString(
            (string) $this->issuedInvoicesQuery($start, $end, $branchId, $criteria->source)
                ->sum('total')
        );

        $collected = $this->normalizeMoneyString(
            (string) $this->paymentsQuery($start, $end, $branchId, $criteria->paymentMethod)
                ->sum('amount')
        );

        $outstandingTotal = $this->sumOutstandingAt($end, $branchId);
        $collectionRate = $this->computeCollectionRate($billed, $collected);

        $branchBreakdown = $this->buildEnrichedBranchBreakdown($start, $end, $branchId, $criteria);

        $methodBreakdown = $this->paymentsQuery($start, $end, $branchId, $criteria->paymentMethod)
            ->select('method', DB::raw('SUM(amount) as total_collected'))
            ->groupBy('method')
            ->orderBy('method')
            ->get()
            ->map(function ($row): array {
                $method = $row->method;

                return [
                    'method' => $method instanceof \BackedEnum ? $method->value : (string) $method,
                    'total_collected' => $this->normalizeMoneyString((string) $row->total_collected),
                ];
            })
            ->values()
            ->all();

        $aging = $this->buildAgingBuckets($end, $branchId);
        $dailyTrend = $this->buildDailyTrend($start, $end, $branchId, $criteria);
        $recentPayments = $this->buildRecentPayments($start, $end, $branchId, $criteria->paymentMethod);
        $topOutstanding = $this->buildTopOutstanding($end, $branchId);

        $report = [
            'summary' => [
                'billed' => $billed,
                'collected' => $collected,
                'outstanding' => $outstandingTotal,
                'collection_rate' => $collectionRate,
            ],
            'daily_trend' => $dailyTrend,
            'branch_breakdown' => $branchBreakdown,
            'method_breakdown' => $methodBreakdown,
            'aging' => $aging,
            'recent_payments' => $recentPayments,
            'top_outstanding' => $topOutstanding,
        ];

        if ($this->insuranceReportingEnabled()) {
            $report['insurance_split'] = $this->buildInsuranceSplit($start, $end, $branchId);
        }

        return $report;
    }

    /**
     * @return array<int, array<int, string>>
     */
    public function toCsvRows(array $report): array
    {
        $rows = [
            ['Section', 'Label', 'Value'],
            ['Summary', 'Billed', (string) $report['summary']['billed']],
            ['Summary', 'Collected', (string) $report['summary']['collected']],
            ['Summary', 'Outstanding', (string) $report['summary']['outstanding']],
            ['Summary', 'Collection rate', (string) ($report['summary']['collection_rate'] ?? '—')],
            ['', '', ''],
            ['Daily Trend', 'Date', 'Billed / Collected'],
        ];

        foreach ($report['daily_trend']['labels'] ?? [] as $index => $label) {
            $billed = $report['daily_trend']['billed'][$index] ?? '0';
            $collected = $report['daily_trend']['collected'][$index] ?? '0';
            $rows[] = ['Daily Trend', (string) $label, (string) $billed.' / '.(string) $collected];
        }

        $rows[] = ['', '', ''];
        $rows[] = ['Branch Breakdown', 'Branch', 'Billed / Collected / Outstanding'];

        foreach ($report['branch_breakdown'] as $branch) {
            $rows[] = [
                'Branch Breakdown',
                (string) $branch['branch_name'],
                sprintf(
                    '%s / %s / %s',
                    (string) ($branch['billed'] ?? $branch['total_billed'] ?? '0'),
                    (string) ($branch['total_collected'] ?? '0'),
                    (string) ($branch['outstanding'] ?? '0'),
                ),
            ];
        }

        $rows[] = ['', '', ''];
        $rows[] = ['Method Breakdown', 'Method', 'Total Collected'];

        foreach ($report['method_breakdown'] as $method) {
            $rows[] = ['Method Breakdown', (string) $method['method'], (string) $method['total_collected']];
        }

        $rows[] = ['', '', ''];
        $rows[] = ['Aging', 'Bucket', 'Amount / Count'];

        foreach ($report['aging'] as $aging) {
            $rows[] = [
                'Aging',
                (string) $aging['bucket'],
                (string) $aging['amount'].' ('.(string) $aging['count'].')',
            ];
        }

        $rows[] = ['', '', ''];
        $rows[] = ['Recent Payments', 'Date', 'Amount / Method / Branch'];

        foreach ($report['recent_payments'] ?? [] as $payment) {
            $client = ClientIdentity::fromArray($payment['client'] ?? []);
            $rows[] = [
                'Recent Payments',
                (string) $payment['received_at'],
                sprintf(
                    '%s %s (%s) — %s — %s — %s',
                    (string) $payment['amount'],
                    (string) $payment['currency'],
                    (string) $payment['method'],
                    $client->displayWithIdentifier(),
                    (string) $payment['branch_name'],
                    (string) ($payment['cashier_name'] ?? __('N/A')),
                ),
            ];
        }

        $rows[] = ['', '', ''];
        $rows[] = ['Top Outstanding', 'Invoice', 'Balance / Days overdue'];

        foreach ($report['top_outstanding'] ?? [] as $invoice) {
            $client = ClientIdentity::fromArray($invoice['client'] ?? []);
            $rows[] = [
                'Top Outstanding',
                (string) $invoice['invoice_number'],
                sprintf(
                    '%s — %s (%s days)',
                    $client->displayWithIdentifier(),
                    (string) $invoice['balance'],
                    (string) $invoice['days_overdue'],
                ),
            ];
        }

        if (isset($report['insurance_split'])) {
            $rows[] = ['', '', ''];
            $rows[] = ['Insurance Split', 'Type', 'Amount'];
            $rows[] = ['Insurance Split', 'Patient responsibility', (string) $report['insurance_split']['patient_amount']];
            $rows[] = ['Insurance Split', 'Insurer expected', (string) $report['insurance_split']['insurer_amount']];
        }

        return $rows;
    }

    /**
     * @return array{labels: list<string>, billed: list<string>, collected: list<string>}
     */
    protected function buildDailyTrend(
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $branchId,
        BillingReportCriteria $criteria
    ): array {
        $labels = [];
        $billedSeries = [];
        $collectedSeries = [];

        $cursor = $start->copy()->startOfDay();
        $lastDay = $end->copy()->startOfDay();

        while ($cursor->lte($lastDay)) {
            $dayStart = $cursor->copy()->startOfDay();
            $dayEnd = $cursor->copy()->endOfDay();

            $dayBilled = $this->normalizeMoneyString(
                (string) $this->issuedInvoicesQuery($dayStart, $dayEnd, $branchId, $criteria->source)->sum('total')
            );

            $dayCollected = $this->normalizeMoneyString(
                (string) $this->paymentsQuery($dayStart, $dayEnd, $branchId, $criteria->paymentMethod)->sum('amount')
            );

            $labels[] = $cursor->toDateString();
            $billedSeries[] = $dayBilled;
            $collectedSeries[] = $dayCollected;

            $cursor->addDay();
        }

        return [
            'labels' => $labels,
            'billed' => $billedSeries,
            'collected' => $collectedSeries,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildEnrichedBranchBreakdown(
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $branchId,
        BillingReportCriteria $criteria
    ): array {
        $branchesQuery = Branch::query()->orderBy('name');
        if ($branchId) {
            $branchesQuery->where('id', $branchId);
        }

        return $branchesQuery->get()->map(function (Branch $branch) use ($start, $end, $criteria): array {
            $bid = (string) $branch->id;

            $billed = $this->normalizeMoneyString(
                (string) $this->issuedInvoicesQuery($start, $end, $bid, $criteria->source)->sum('total')
            );

            $collected = $this->normalizeMoneyString(
                (string) $this->paymentsQuery($start, $end, $bid, $criteria->paymentMethod)->sum('amount')
            );

            $outstanding = $this->sumOutstandingAt($end, $bid);

            return [
                'branch_name' => (string) $branch->name,
                'billed' => $billed,
                'total_collected' => $collected,
                'outstanding' => $outstanding,
            ];
        })
            ->filter(fn (array $row): bool => bccomp((string) $row['billed'], '0', 2) > 0
                || bccomp((string) $row['total_collected'], '0', 2) > 0
                || bccomp((string) $row['outstanding'], '0', 2) > 0)
            ->values()
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildRecentPayments(
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $branchId,
        ?string $paymentMethod
    ): array {
        return Payment::queryForReportListing(new BillingReportCriteria(
            startDate: $start,
            endDate: $end,
            branchId: $branchId,
            paymentMethod: $paymentMethod,
        ))
            ->limit(50)
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => (string) $payment->id,
                'received_at' => $payment->received_at?->format('Y-m-d H:i') ?? '',
                'client' => $payment->clientIdentity()->toArray(),
                'branch_name' => $payment->branch?->name ?? __('N/A'),
                'cashier_name' => $payment->recorder?->name ?? __('N/A'),
                'method' => $payment->method instanceof PaymentMethod
                    ? $payment->method->value
                    : (string) $payment->method,
                'amount' => $this->normalizeMoneyString((string) $payment->amount),
                'currency' => (string) $payment->currency,
            ])
            ->all();
    }

    /**
     * @return list<array<string, mixed>>
     */
    protected function buildTopOutstanding(CarbonInterface $atDate, ?string $branchId, int $limit = 25): array
    {
        $query = Invoice::query()
            ->withoutGlobalScopes()
            ->with([
                'patient' => fn ($q) => $q->withoutGlobalScopes(),
                'branch',
            ])
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->where(DB::raw('COALESCE(issued_at, created_at)'), '<=', $atDate);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query
            ->whereRaw('(total - amount_paid) > 0')
            ->orderByDesc(DB::raw('(total - amount_paid)'))
            ->limit($limit)
            ->get()
            ->map(function (Invoice $invoice) use ($atDate): array {
                $balance = bcsub((string) $invoice->total, (string) $invoice->amount_paid, 2);

                $anchorDate = $invoice->due_at ?? $invoice->issued_at ?? $invoice->created_at;
                $daysOverdue = $anchorDate ? max(0, (int) $anchorDate->diffInDays($atDate, false)) : 0;

                return [
                    'id' => (string) $invoice->id,
                    'invoice_number' => (string) $invoice->invoice_number,
                    'client' => $invoice->clientIdentity()->toArray(),
                    'branch_name' => $invoice->branch?->name ?? __('N/A'),
                    'issued_at' => $invoice->issued_at?->format('Y-m-d') ?? '',
                    'total' => $this->normalizeMoneyString((string) $invoice->total),
                    'balance' => $balance,
                    'days_overdue' => $daysOverdue,
                    'currency' => (string) $invoice->currency,
                ];
            })
            ->all();
    }

    /**
     * @return array{patient_amount: string, insurer_amount: string}
     */
    protected function buildInsuranceSplit(CarbonInterface $start, CarbonInterface $end, ?string $branchId): array
    {
        $invoiceIds = $this->issuedInvoicesQuery($start, $end, $branchId)
            ->pluck('id');

        if ($invoiceIds->isEmpty()) {
            return ['patient_amount' => '0', 'insurer_amount' => '0'];
        }

        $totals = InvoiceLine::query()
            ->whereIn('invoice_id', $invoiceIds)
            ->selectRaw('COALESCE(SUM(patient_responsibility_amount), 0) as patient_total, COALESCE(SUM(insurance_expected_amount), 0) as insurer_total')
            ->first();

        return [
            'patient_amount' => $this->normalizeMoneyString((string) ($totals->patient_total ?? 0)),
            'insurer_amount' => $this->normalizeMoneyString((string) ($totals->insurer_total ?? 0)),
        ];
    }

    protected function computeCollectionRate(string $billed, string $collected): ?string
    {
        if (bccomp($billed, '0', 2) <= 0) {
            return null;
        }

        $rate = bcmul(bcdiv($collected, $billed, 4), '100', 2);

        return $this->normalizeMoneyString($rate);
    }

    protected function insuranceReportingEnabled(): bool
    {
        return (bool) config('insurance.enabled', false);
    }

    protected function issuedInvoicesQuery(
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $branchId = null,
        ?string $source = null
    ) {
        $query = Invoice::query()
            ->withoutGlobalScopes()
            ->whereIn('status', [
                InvoiceStatus::Issued,
                InvoiceStatus::PartiallyPaid,
                InvoiceStatus::Paid,
            ])
            ->whereBetween(DB::raw('COALESCE(issued_at, created_at)'), [$start, $end]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        if ($source === 'encounter') {
            $query->whereNotNull('encounter_id');
        } elseif ($source === 'pharmacy_pos') {
            $query->where('metadata->source', 'pharmacy_pos');
        } elseif ($source === 'standalone') {
            $query->whereNull('encounter_id')
                ->where(function ($q): void {
                    $q->whereNull('metadata->source')
                        ->orWhere('metadata->source', '!=', 'pharmacy_pos');
                });
        } elseif ($source === 'guest') {
            $query->whereNull('patient_id')->whereNotNull('guest_name');
        }

        return $query;
    }

    protected function paymentsQuery(
        CarbonInterface $start,
        CarbonInterface $end,
        ?string $branchId = null,
        ?string $paymentMethod = null
    ) {
        return Payment::queryForReport(new BillingReportCriteria(
            startDate: $start,
            endDate: $end,
            branchId: $branchId,
            paymentMethod: $paymentMethod,
        ));
    }

    protected function sumOutstandingAt(CarbonInterface $atDate, ?string $branchId = null): string
    {
        $query = Invoice::query()
            ->withoutGlobalScopes()
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->where(DB::raw('COALESCE(issued_at, created_at)'), '<=', $atDate);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $carry = '0';
        $query->select(['id', 'total', 'amount_paid'])
            ->chunkById(500, function ($invoices) use (&$carry): void {
                foreach ($invoices as $invoice) {
                    $carry = bcadd(
                        $carry,
                        bcsub((string) $invoice->total, (string) $invoice->amount_paid, 2),
                        2
                    );
                }
            });

        return $carry;
    }

    /**
     * @return array<int, array{bucket:string, amount:string, count:int}>
     */
    protected function buildAgingBuckets(CarbonInterface $atDate, ?string $branchId = null): array
    {
        $buckets = [
            'Current' => ['amount' => '0', 'count' => 0],
            '1-30 days' => ['amount' => '0', 'count' => 0],
            '31-60 days' => ['amount' => '0', 'count' => 0],
            '61-90 days' => ['amount' => '0', 'count' => 0],
            '90+ days' => ['amount' => '0', 'count' => 0],
        ];

        $query = Invoice::query()
            ->withoutGlobalScopes()
            ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
            ->where(DB::raw('COALESCE(issued_at, created_at)'), '<=', $atDate);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        $query->select(['id', 'total', 'amount_paid', 'due_at', 'issued_at', 'created_at'])
            ->chunkById(500, function ($invoices) use (&$buckets, $atDate): void {
                foreach ($invoices as $invoice) {
                    $outstanding = bcsub((string) $invoice->total, (string) $invoice->amount_paid, 2);
                    if (bccomp($outstanding, '0', 2) <= 0) {
                        continue;
                    }

                    $anchorDate = $invoice->due_at ?? $invoice->issued_at ?? $invoice->created_at;
                    $ageDays = $anchorDate ? $anchorDate->diffInDays($atDate, false) : 0;

                    $bucket = match (true) {
                        $ageDays <= 0 => 'Current',
                        $ageDays <= 30 => '1-30 days',
                        $ageDays <= 60 => '31-60 days',
                        $ageDays <= 90 => '61-90 days',
                        default => '90+ days',
                    };

                    $buckets[$bucket]['amount'] = bcadd($buckets[$bucket]['amount'], $outstanding, 2);
                    $buckets[$bucket]['count']++;
                }
            });

        return collect($buckets)
            ->map(fn (array $values, string $bucket): array => [
                'bucket' => $bucket,
                'amount' => $values['amount'],
                'count' => $values['count'],
            ])
            ->values()
            ->all();
    }
}
