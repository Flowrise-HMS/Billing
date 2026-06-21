<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;

class AgingDonutChartWidget extends ChartWidget
{
    use HasWidgetShield;

    protected static ?string $cluster = BillingCluster::class;

    protected ?string $heading = 'Outstanding by aging bucket';

    protected int|string|array $columnSpan = 1;
    protected static bool $isDiscovered = true;

    #[Reactive]
    public ?array $reportPayload = null;

    protected function getData(): array
    {
        $aging = $this->reportPayload['aging'] ?? [];

        if ($aging === []) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => array_column($aging, 'bucket'),
            'datasets' => [
                [
                    'data' => array_map(fn (array $row) => (float) ($row['amount'] ?? 0), $aging),
                    'backgroundColor' => ['#16a34a', '#eab308', '#f97316', '#ef4444', '#991b1b'],
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getOptions(): array
    {
        return [
            'responsive' => true,
            'maintainAspectRatio' => false,
        ];
    }
}
