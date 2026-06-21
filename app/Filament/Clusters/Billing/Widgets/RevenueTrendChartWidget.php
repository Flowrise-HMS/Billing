<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;

class RevenueTrendChartWidget extends ChartWidget
{
    use InteractsWithWidgetShield;

    protected static ?string $cluster = BillingCluster::class;

    protected ?string $heading = 'Daily billed vs collected';

    protected int|string|array $columnSpan = 1;

    protected static bool $isDiscovered = true;

    #[Reactive]
    public ?array $reportPayload = null;

    protected function getData(): array
    {
        $trend = $this->reportPayload['daily_trend'] ?? ['labels' => [], 'billed' => [], 'collected' => []];

        if ($trend['labels'] === []) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => $trend['labels'],
            'datasets' => [
                [
                    'label' => __('Billed'),
                    'data' => array_map(fn ($v) => (float) $v, $trend['billed']),
                    'borderColor' => '#9ca3af',
                    'backgroundColor' => 'rgba(156, 163, 175, 0.1)',
                    'tension' => 0.3,
                    'fill' => false,
                ],
                [
                    'label' => __('Collected'),
                    'data' => array_map(fn ($v) => (float) $v, $trend['collected']),
                    'borderColor' => '#16a34a',
                    'backgroundColor' => 'rgba(22, 163, 74, 0.1)',
                    'tension' => 0.3,
                    'fill' => false,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'line';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
            'scales' => [
                'y' => ['beginAtZero' => true],
            ],
        ];
    }
}
