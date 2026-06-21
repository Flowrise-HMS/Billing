<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Widgets\Concerns\InteractsWithReportPayload;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;

class BranchSummaryTableWidget extends BaseWidget
{
    use InteractsWithReportPayload;
    use InteractsWithWidgetShield;

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
                TextColumn::make('billed')
                    ->label(__('Billed'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('total_collected')
                    ->label(__('Collected'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('outstanding')
                    ->label(__('Outstanding'))
                    ->numeric(decimalPlaces: 2),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('No data'));
    }
}
