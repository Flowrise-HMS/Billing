<?php

namespace Modules\Billing\Filament\Clusters\Billing\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\WidgetConfiguration;
use Modules\Billing\Data\BillingReportCriteria;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Widgets\AgingBucketsTableWidget;
use Modules\Billing\Filament\Clusters\Billing\Widgets\AgingDonutChartWidget;
use Modules\Billing\Filament\Clusters\Billing\Widgets\BranchCollectionsBarChartWidget;
use Modules\Billing\Filament\Clusters\Billing\Widgets\BranchSummaryTableWidget;
use Modules\Billing\Filament\Clusters\Billing\Widgets\FinancialStatsWidget;
use Modules\Billing\Filament\Clusters\Billing\Widgets\InsuranceSplitDonutChartWidget;
use Modules\Billing\Filament\Clusters\Billing\Widgets\PaymentMethodBreakdownTableWidget;
use Modules\Billing\Filament\Clusters\Billing\Widgets\PaymentMethodDonutChartWidget;
use Modules\Billing\Filament\Clusters\Billing\Widgets\RecentPaymentsTableWidget;
use Modules\Billing\Filament\Clusters\Billing\Widgets\RevenueTrendChartWidget;
use Modules\Billing\Filament\Clusters\Billing\Widgets\TopOutstandingInvoicesTableWidget;
use Modules\Billing\Services\RevenueReportService;
use Modules\Core\Models\Branch;

class RevenueReport extends Page
{
    use HasPageShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedPresentationChartBar;

    protected string $view = 'billing::filament.clusters.billing.pages.revenue-report';

    public ?string $preset = 'today';

    public ?string $startDate = null;

    public ?string $endDate = null;

    public ?string $branchId = null;

    public ?string $paymentMethod = null;

    /**
     * @var array<string, mixed>
     */
    public array $report = [];

    public function mount(): void
    {
        $this->branchId = request()->query('branch_id');
        $this->paymentMethod = request()->query('payment_method');

        $hasCustomDates = request()->has('start_date') && request()->has('end_date');
        $this->preset = $hasCustomDates ? 'custom' : (string) request()->query('preset', 'today');

        $criteria = BillingReportCriteria::fromRequest(request()->query());

        $this->startDate = $criteria->startDate->toDateString();
        $this->endDate = $criteria->endDate->toDateString();

        $this->loadReport();
    }

    /**
     * @return array<string, string>
     */
    public function getBranchOptionsProperty(): array
    {
        return Branch::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<string, string>
     */
    public function getPaymentMethodOptionsProperty(): array
    {
        $options = ['' => __('All methods')];
        foreach (PaymentMethod::cases() as $method) {
            $options[$method->value] = (string) $method->getLabel();
        }

        return $options;
    }

    public function presetUrl(string $preset): string
    {
        return static::getUrl([
            'preset' => $preset,
            'branch_id' => $this->branchId,
            'payment_method' => $this->paymentMethod,
        ]);
    }

    public function exportCsv(RevenueReportService $reports)
    {
        $criteria = $this->buildCriteria();
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
        }, $filename, [
            'Content-Type' => 'text/csv',
        ]);
    }

    /**
     * @return array<class-string|int, class-string|WidgetConfiguration>
     */
    protected function getFooterWidgets(): array
    {
        $payload = ['reportPayload' => $this->report];

        $widgets = [
            FinancialStatsWidget::class,
            RevenueTrendChartWidget::make($payload),
            BranchCollectionsBarChartWidget::make($payload),
            PaymentMethodDonutChartWidget::make($payload),
            AgingDonutChartWidget::make($payload),
        ];

        if (isset($this->report['insurance_split'])) {
            $widgets[] = InsuranceSplitDonutChartWidget::make($payload);
        }

        return $widgets;
    }

    /**
     * @return array<class-string|int, class-string|WidgetConfiguration>
     */
    public function getReportTableWidgets(): array
    {
        $payload = ['reportPayload' => $this->report];

        return [
            BranchSummaryTableWidget::make($payload),
            PaymentMethodBreakdownTableWidget::make($payload),
            AgingBucketsTableWidget::make($payload),
            RecentPaymentsTableWidget::make($payload),
            TopOutstandingInvoicesTableWidget::make($payload),
        ];
    }

    protected function loadReport(): void
    {
        $this->report = app(RevenueReportService::class)->buildFromCriteria($this->buildCriteria());
    }

    protected function buildCriteria(): BillingReportCriteria
    {
        return BillingReportCriteria::fromRequest([
            'preset' => $this->preset,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'branch_id' => $this->branchId,
            'payment_method' => $this->paymentMethod,
        ]);
    }
}
