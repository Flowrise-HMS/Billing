<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;
use Modules\Billing\Filament\Clusters\Billing\Widgets\Concerns\InteractsWithReportPayload;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;

class TopOutstandingInvoicesTableWidget extends BaseWidget
{
    use InteractsWithReportPayload;
    use InteractsWithWidgetShield;

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
                TextColumn::make('patient_name')
                    ->label(__('Patient')),
                TextColumn::make('branch_name')
                    ->label(__('Branch')),
                TextColumn::make('issued_at')
                    ->label(__('Issued')),
                TextColumn::make('balance')
                    ->label(__('Balance'))
                    ->formatStateUsing(fn (mixed $state, array $record): string => number_format((float) $state, 2).' '.($record['currency'] ?? '')),
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
            ->paginated(false)
            ->emptyStateHeading(__('No outstanding invoices'));
    }
}
