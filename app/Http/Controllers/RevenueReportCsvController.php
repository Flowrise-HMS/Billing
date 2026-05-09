<?php

namespace Modules\Billing\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Modules\Billing\Services\RevenueReportService;

class RevenueReportCsvController extends BillingController
{
    public function __invoke(Request $request, RevenueReportService $reports)
    {
        $startDate = Carbon::parse((string) $request->query('start_date', now()->startOfMonth()->toDateString()));
        $endDate = Carbon::parse((string) $request->query('end_date', now()->toDateString()));
        $branchId = $request->query('branch_id');

        $report = $reports->build($startDate, $endDate, is_string($branchId) && $branchId !== '' ? $branchId : null);
        $rows = $reports->toCsvRows($report);

        $filename = sprintf('revenue-report-%s-to-%s.csv', $startDate->toDateString(), $endDate->toDateString());

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
