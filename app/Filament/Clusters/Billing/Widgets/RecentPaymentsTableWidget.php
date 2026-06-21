<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Widgets\Concerns\InteractsWithReportPayload;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class RecentPaymentsTableWidget extends BaseWidget
{
    use InteractsWithReportPayload;
    use InteractsWithWidgetShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Recent payments'))
            ->records(fn (): array => $this->reportRows('recent_payments', 'id'))
            ->columns([
                TextColumn::make('received_at')
                    ->label(__('Date')),
                TextColumn::make('patient_name')
                    ->label(__('Patient')),
                TextColumn::make('branch_name')
                    ->label(__('Branch')),
                TextColumn::make('method')
                    ->label(__('Method'))
                    ->formatStateUsing(fn (mixed $state): string => PaymentMethod::tryFrom((string) $state)?->getLabel() ?? (string) $state),
                CurrencyColumn::make('amount')
                    ->label(__('Amount'))
                    ->currency(fn (array $record): ?string => isset($record['currency']) ? (string) $record['currency'] : null),
            ])
            ->recordActions([
                Action::make('receipt')
                    ->label(__('Receipt'))
                    ->icon('heroicon-m-document-text')
                    ->url(fn (array $record): string => route('billing.payments.receipt', $record['id']))
                    ->openUrlInNewTab(),
            ])
            ->paginated(false)
            ->emptyStateHeading(__('No payments in this period'));
    }
}
