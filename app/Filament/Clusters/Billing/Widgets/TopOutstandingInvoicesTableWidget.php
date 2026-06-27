<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;
use Modules\Billing\Filament\Clusters\Billing\Widgets\Concerns\InteractsWithReportPayload;
use Modules\Billing\Filament\Clusters\Billing\Widgets\Concerns\SummarizesReportTableColumns;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Core\Filament\Support\ClientIdentityColumn;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;
use Modules\Core\Support\ClientIdentity;

class TopOutstandingInvoicesTableWidget extends BaseWidget
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
            ->heading(__('Top outstanding invoices'))
            ->records(fn (): array => $this->reportRows('top_outstanding', 'id'))
            ->columns([
                TextColumn::make('invoice_number')
                    ->label(__('Invoice #')),
                ClientIdentityColumn::make(resolve: fn (array $record): ClientIdentity => ClientIdentity::fromArray($record['client'] ?? [])),
                TextColumn::make('branch_name')
                    ->label(__('Branch')),
                TextColumn::make('issued_at')
                    ->label(__('Issued')),
                CurrencyColumn::make('balance')
                    ->label(__('Balance'))
                    ->currency(fn (array $record): ?string => isset($record['currency']) ? (string) $record['currency'] : null)
                    ->summarize($this->reportMoneySumSummarizer('balance', 'currency')),
                TextColumn::make('days_overdue')
                    ->label(__('Days overdue'))
                    ->numeric(),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('View'))
                    ->icon('heroicon-m-eye')
                    ->url(fn (array $record): string => InvoiceResource::getUrl('view', ['record' => $record['id']])),
            ])
            ->summaries(pageCondition: false)
            ->paginated(false)
            ->emptyStateHeading(__('No outstanding invoices'));
    }
}
