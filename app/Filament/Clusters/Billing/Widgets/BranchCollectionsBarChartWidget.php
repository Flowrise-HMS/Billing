<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;

class BranchCollectionsBarChartWidget extends ChartWidget
{
    use InteractsWithWidgetShield;

    protected static ?string $cluster = BillingCluster::class;

    protected ?string $heading = 'Collections by branch';

    protected int|string|array $columnSpan = 1;

    protected static bool $isDiscovered = true;

    #[Reactive]
    public ?array $reportPayload = null;

    protected function getData(): array
    {
        $branches = $this->reportPayload['branch_breakdown'] ?? [];

        if ($branches === []) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => array_column($branches, 'branch_name'),
            'datasets' => [
                [
                    'label' => __('Collected'),
                    'data' => array_map(
                        fn (array $row) => (float) ($row['total_collected'] ?? 0),
                        $branches
                    ),
                    'backgroundColor' => '#3b82f6',
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'x' => ['beginAtZero' => true],
            ],
        ];
    }
}
