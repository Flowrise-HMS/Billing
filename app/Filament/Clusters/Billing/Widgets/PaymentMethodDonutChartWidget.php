<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Concerns\InteractsWithWidgetShield;

class PaymentMethodDonutChartWidget extends ChartWidget
{
    use InteractsWithWidgetShield;

    protected static ?string $cluster = BillingCluster::class;

    protected ?string $heading = 'Payment method mix';

    protected int|string|array $columnSpan = 1;

    protected static bool $isDiscovered = true;

    #[Reactive]
    public ?array $reportPayload = null;

    protected function getData(): array
    {
        $methods = $this->reportPayload['method_breakdown'] ?? [];

        if ($methods === []) {
            return ['labels' => [], 'datasets' => []];
        }

        $labels = [];
        foreach ($methods as $row) {
            $method = PaymentMethod::tryFrom((string) $row['method']);
            $labels[] = $method?->getLabel() ?? (string) $row['method'];
        }

        return [
            'labels' => $labels,
            'datasets' => [
                [
                    'data' => array_map(
                        fn (array $row) => (float) ($row['total_collected'] ?? 0),
                        $methods
                    ),
                    'backgroundColor' => ['#6b7280', '#3b82f6', '#06b6d4', '#16a34a', '#f59e0b'],
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
