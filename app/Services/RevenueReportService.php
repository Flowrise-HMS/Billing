<?php

namespace Modules\Billing\Services;

use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;

class RevenueReportService
{
    /**
     * @return array{
     *   summary: array<string, string>,
     *   branch_breakdown: array<int, array<string, mixed>>,
     *   method_breakdown: array<int, array<string, mixed>>,
     *   aging: array<int, array{bucket:string, amount:string, count:int}>
     * }
     */
    public function build(CarbonInterface $startDate, CarbonInterface $endDate, ?string $branchId = null): array
    {
        $start = $startDate->copy()->startOfDay();
        $end = $endDate->copy()->endOfDay();

        $billed = $this->normalizeMoneyString(
            (string) $this->issuedInvoicesQuery($start, $end, $branchId)
                ->sum('total')
        );

        $collected = $this->normalizeMoneyString(
            (string) $this->paymentsQuery($start, $end, $branchId)
                ->sum('amount')
        );

        $branchBreakdown = $this->paymentsQuery($start, $end, $branchId)
            ->select('branches.name as branch_name', DB::raw('SUM(payments.amount) as total_collected'))
            ->join('branches', 'branches.id', '=', 'payments.branch_id')
            ->groupBy('branches.id', 'branches.name')
            ->orderBy('branches.name')
            ->get()
            ->map(fn ($row): array => [
                'branch_name' => (string) $row->branch_name,
                'total_collected' => $this->normalizeMoneyString((string) $row->total_collected),
            ])
            ->values()
            ->all();

        $methodBreakdown = $this->paymentsQuery($start, $end, $branchId)
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

        $outstandingTotal = $this->sumOutstandingAt($end, $branchId);
        $aging = $this->buildAgingBuckets($end, $branchId);

        return [
            'summary' => [
                'billed' => $billed,
                'collected' => $collected,
                'outstanding' => $outstandingTotal,
            ],
            'branch_breakdown' => $branchBreakdown,
            'method_breakdown' => $methodBreakdown,
            'aging' => $aging,
        ];
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
            ['', '', ''],
            ['Branch Breakdown', 'Branch', 'Total Collected'],
        ];

        foreach ($report['branch_breakdown'] as $branch) {
            $rows[] = ['Branch Breakdown', (string) $branch['branch_name'], (string) $branch['total_collected']];
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

        return $rows;
    }

    protected function issuedInvoicesQuery(CarbonInterface $start, CarbonInterface $end, ?string $branchId = null)
    {
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

        return $query;
    }

    protected function paymentsQuery(CarbonInterface $start, CarbonInterface $end, ?string $branchId = null)
    {
        $query = Payment::query()
            ->whereBetween('received_at', [$start, $end]);

        if ($branchId) {
            $query->where('branch_id', $branchId);
        }

        return $query;
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

    protected function normalizeMoneyString(string $value): string
    {
        if (! str_contains($value, '.')) {
            return $value;
        }

        return rtrim(rtrim($value, '0'), '.');
    }
}
