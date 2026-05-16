<?php

namespace Modules\Billing\Filament\Clusters\Billing\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Carbon\Carbon;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Services\RevenueReportService;
use Modules\Core\Models\Branch;

class RevenueReport extends Page
{
    use HasPageShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected string $view = 'billing::filament.clusters.billing.pages.revenue-report';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?string $branchId = null;

    /**
     * @var array<string, mixed>
     */
    public array $report = [];

    public function mount(): void
    {
        $this->startDate = request()->query('start_date', now()->startOfMonth()->toDateString());
        $this->endDate = request()->query('end_date', now()->toDateString());
        $this->branchId = request()->query('branch_id');

        $this->loadReport();
    }

    /**
     * @return array<string, string>
     */
    public function getBranchOptionsProperty(): array
    {
        return Branch::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function exportCsv(RevenueReportService $reports)
    {
        $report = $reports->build(
            Carbon::parse((string) $this->startDate),
            Carbon::parse((string) $this->endDate),
            $this->branchId
        );

        $rows = $reports->toCsvRows($report);
        $filename = sprintf(
            'revenue-report-%s-to-%s.csv',
            (string) $this->startDate,
            (string) $this->endDate
        );

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
            fclose($output);
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    protected function loadReport(): void
    {
        $this->report = app(RevenueReportService::class)->build(
            Carbon::parse((string) $this->startDate),
            Carbon::parse((string) $this->endDate),
            $this->branchId
        );
    }
}
