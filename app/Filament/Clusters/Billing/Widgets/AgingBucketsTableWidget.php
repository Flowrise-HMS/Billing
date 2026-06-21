<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Widgets\Concerns\InteractsWithReportPayload;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class AgingBucketsTableWidget extends BaseWidget
{
    use InteractsWithReportPayload;
    use InteractsWithWidgetShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('A/R aging buckets'))
            ->records(fn (): array => $this->reportRows('aging', 'bucket'))
            ->columns([
                TextColumn::make('bucket')
                    ->label(__('Bucket')),
                CurrencyColumn::make('amount')
                    ->label(__('Amount')),
                TextColumn::make('count')
                    ->label(__('Invoices'))
                    ->numeric(),
            ])
            ->paginated(false);
    }
}
