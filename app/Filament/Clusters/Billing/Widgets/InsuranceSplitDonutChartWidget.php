<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\ChartWidget;
use Livewire\Attributes\Reactive;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;

class InsuranceSplitDonutChartWidget extends ChartWidget
{
    use HasWidgetShield;

    protected static ?string $cluster = BillingCluster::class;

    protected ?string $heading = 'Insurance vs patient responsibility';

    protected int|string|array $columnSpan = 1;
    protected static bool $isDiscovered = true;

    #[Reactive]
    public ?array $reportPayload = null;

    protected function getData(): array
    {
        $split = $this->reportPayload['insurance_split'] ?? null;

        if ($split === null) {
            return ['labels' => [], 'datasets' => []];
        }

        return [
            'labels' => [__('Patient'), __('Insurer')],
            'datasets' => [
                [
                    'data' => [
                        (float) ($split['patient_amount'] ?? 0),
                        (float) ($split['insurer_amount'] ?? 0),
                    ],
                    'backgroundColor' => ['#6366f1', '#14b8a6'],
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
