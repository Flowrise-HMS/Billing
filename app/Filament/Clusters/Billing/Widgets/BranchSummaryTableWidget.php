<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Widgets\Concerns\InteractsWithReportPayload;
use Modules\Billing\Filament\Clusters\Billing\Widgets\Concerns\SummarizesReportTableColumns;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class BranchSummaryTableWidget extends BaseWidget
{
    use InteractsWithReportPayload;
    use InteractsWithWidgetShield;
    use SummarizesReportTableColumns;

    protected static ?string $cluster = BillingCluster::class;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Branch summary'))
            ->records(fn (): array => $this->reportRows('branch_breakdown', 'branch_name'))
            ->columns([
                TextColumn::make('branch_name')
                    ->label(__('Branch')),
                CurrencyColumn::make('billed')
                    ->label(__('Billed'))
                    ->summarize($this->reportMoneySumSummarizer('billed')),
                CurrencyColumn::make('total_collected')
                    ->label(__('Collected'))
                    ->summarize($this->reportMoneySumSummarizer('total_collected')),
                CurrencyColumn::make('outstanding')
                    ->label(__('Outstanding'))
                    ->summarize($this->reportMoneySumSummarizer('outstanding')),
            ])
            ->summaries(pageCondition: false)
            ->paginated(false)
            ->emptyStateHeading(__('No data'));
    }
}
