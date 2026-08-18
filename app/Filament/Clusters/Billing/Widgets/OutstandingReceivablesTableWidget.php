<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Support\SearchesPatients;
use Modules\Billing\Models\Invoice;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Core\Filament\Support\ClientIdentityColumn;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class OutstandingReceivablesTableWidget extends BaseWidget
{
    use InteractsWithWidgetShield;
    use SearchesPatients;

    protected static ?string $cluster = BillingCluster::class;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Outstanding receivables'))
            ->query(fn (): Builder => Invoice::query()
                ->withoutGlobalScopes()
                ->select('patient_id')
                ->selectRaw('MAX(id) as id')
                ->selectRaw('COUNT(*) as invoice_count')
                ->selectRaw('SUM(total - amount_paid) as outstanding')
                ->selectRaw('MAX(currency) as currency')
                ->with(['patient' => fn ($query) => $query->withoutGlobalScopes()])
                ->whereNotIn('status', [InvoiceStatus::Draft, InvoiceStatus::Void])
                ->whereRaw('total > amount_paid')
                ->groupBy('patient_id'))
            ->columns([
                ClientIdentityColumn::make()
                    ->searchable(query: self::patientSearchQuery()),
                TextColumn::make('invoice_count')
                    ->label(__('Invoices'))
                    ->numeric(),
                CurrencyColumn::make('outstanding')
                    ->label(__('Outstanding'))
                    ->currency(fn ($record): string => (string) ($record->currency ?? 'GHS')),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label(__('Branch'))
                    ->relationship('branch', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn (): ?string => Auth::user()?->branch_id),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(InvoiceStatus::class)
                    ->attribute('status'),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->defaultSort('outstanding', 'desc')
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading(__('No outstanding receivables'));
    }
}
