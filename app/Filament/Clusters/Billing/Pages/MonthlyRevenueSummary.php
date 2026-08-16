<?php

namespace Modules\Billing\Filament\Clusters\Billing\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Services\MonthlyRevenueService;
use Modules\Core\Models\Branch;

class MonthlyRevenueSummary extends Page
{
    use HasPageShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected string $view = 'billing::filament.clusters.billing.pages.monthly-revenue-summary';

    public ?string $month = null;

    public ?string $branchId = null;

    /**
     * @var array<string, mixed>
     */
    public array $summary = [];

    public function mount(): void
    {
        $this->month = request()->query('month', now()->format('Y-m'));
        $this->branchId = request()->query('branch_id', Auth::user()?->branch_id);
    }

    public function loadSummary(): void
    {
        if ($this->branchId === null) {
            $this->summary = [];

            return;
        }

        $branch = Branch::query()->findOrFail($this->branchId);
        $this->summary = app(MonthlyRevenueService::class)->monthly($branch, \Illuminate\Support\Carbon::parse($this->month));
    }

    /**
     * @return array<string, string>
     */
    public function getBranchesProperty(): array
    {
        return Branch::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    public function exportCsv(): \Symfony\Component\HttpFoundation\StreamedResponse
    {
        $summary = $this->summary;

        return response()->streamDownload(function () use ($summary) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Metric', 'Amount']);
            fputcsv($out, ['Revenue total', $summary['revenue_total'] ?? '0']);
            fputcsv($out, ['Refunds', $summary['refunds_total'] ?? '0']);
            fputcsv($out, ['Net revenue', $summary['net_revenue'] ?? '0']);
            foreach (($summary['revenue_by_method'] ?? []) as $method => $amount) {
                fputcsv($out, ['Revenue - '.$method, $amount]);
            }
            fclose($out);
        }, 'monthly-revenue-'.$this->month.'.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportPdf(): \Symfony\Component\HttpFoundation\Response
    {
        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('billing::pdf.monthly-revenue-summary', [
            'month' => $this->month,
            'branch' => Branch::query()->find($this->branchId),
            'summary' => $this->summary,
        ])->setPaper('a4', 'landscape');

        return $pdf->download('monthly-revenue-'.$this->month.'.pdf');
    }
}
