<?php

namespace Modules\Billing\Http\Controllers;

use Illuminate\Http\Request;
use Modules\Billing\Data\BillingReportCriteria;
use Modules\Billing\Services\RevenueReportService;

class RevenueReportCsvController extends BillingController
{
    public function __invoke(Request $request, RevenueReportService $reports)
    {
        $criteria = BillingReportCriteria::fromRequest($request->query());
        $report = $reports->buildFromCriteria($criteria);
        $rows = $reports->toCsvRows($report);

        $filename = sprintf(
            'revenue-report-%s-to-%s.csv',
            $criteria->startDate->toDateString(),
            $criteria->endDate->toDateString()
        );

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
