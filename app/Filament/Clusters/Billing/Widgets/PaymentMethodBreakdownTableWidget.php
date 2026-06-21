<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Widgets\Concerns\InteractsWithReportPayload;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;

class PaymentMethodBreakdownTableWidget extends BaseWidget
{
    use InteractsWithReportPayload;
    use InteractsWithWidgetShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Collections by payment method'))
            ->records(fn (): array => $this->reportRows('method_breakdown', 'method'))
            ->columns([
                TextColumn::make('method')
                    ->label(__('Method'))
                    ->formatStateUsing(fn (mixed $state): string => PaymentMethod::tryFrom((string) $state)?->getLabel() ?? (string) $state),
                TextColumn::make('total_collected')
                    ->label(__('Collected'))
                    ->numeric(decimalPlaces: 2),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('No data'));
    }
}
