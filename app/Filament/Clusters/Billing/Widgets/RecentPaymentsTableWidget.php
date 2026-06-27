<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Widgets\Concerns\InteractsWithReportPayload;
use Modules\Billing\Filament\Clusters\Billing\Widgets\Concerns\SummarizesReportTableColumns;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Core\Filament\Support\ClientIdentityColumn;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;
use Modules\Core\Support\ClientIdentity;

class RecentPaymentsTableWidget extends BaseWidget
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
            ->heading(__('Recent payments'))
            ->records(fn (): array|LengthAwarePaginator => $this->paginateReportRows(
                $this->reportRows('recent_payments', 'id'),
            ))
            ->columns([
                TextColumn::make('received_at')
                    ->label(__('Date')),
                ClientIdentityColumn::make(resolve: fn (array $record): ClientIdentity => ClientIdentity::fromArray($record['client'] ?? [])),
                TextColumn::make('cashier_name')
                    ->label(__('Cashier')),
                TextColumn::make('branch_name')
                    ->label(__('Branch')),
                TextColumn::make('method')
                    ->label(__('Method'))
                    ->formatStateUsing(fn (mixed $state): string => PaymentMethod::tryFrom((string) $state)?->getLabel() ?? (string) $state),
                CurrencyColumn::make('amount')
                    ->label(__('Amount'))
                    ->currency(fn (array $record): ?string => isset($record['currency']) ? (string) $record['currency'] : null)
                    ->summarize($this->reportMoneySumSummarizer('amount', 'currency')),
            ])
            ->summaries(pageCondition: false)
            ->recordActions([
                Action::make('receipt')
                    ->label(__('Receipt'))
                    ->icon('heroicon-m-document-text')
                    ->url(fn (array $record): string => route('billing.payments.receipt', $record['id']))
                    ->openUrlInNewTab(),
            ])
            ->paginated([10, 50, 100, 150, 200, 500])
            ->emptyStateHeading(__('No payments in this period'));
    }
}
