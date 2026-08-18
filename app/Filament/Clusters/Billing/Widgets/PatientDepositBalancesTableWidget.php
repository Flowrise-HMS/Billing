<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Enums\PatientDepositStatus;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Support\SearchesPatients;
use Modules\Billing\Models\PatientDeposit;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Core\Filament\Support\ClientIdentityColumn;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;
use Modules\Core\Support\ClientIdentityResolver;

class PatientDepositBalancesTableWidget extends BaseWidget
{
    use InteractsWithWidgetShield;
    use SearchesPatients;

    protected static ?string $cluster = BillingCluster::class;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Patient deposit balances'))
            ->query(fn (): Builder => PatientDeposit::query()
                ->select('patient_id')
                ->selectRaw('MAX(id) as id')
                ->selectRaw('SUM(amount) as deposited')
                ->selectRaw('SUM(amount - unallocated_balance) as applied')
                ->selectRaw('SUM(unallocated_balance) as remaining')
                ->selectRaw('MAX(currency) as currency')
                ->with(['patient' => fn ($query) => $query->withoutGlobalScopes()])
                ->groupBy('patient_id'))
            ->columns([
                ClientIdentityColumn::make(
                    resolve: fn (PatientDeposit $record) => ClientIdentityResolver::resolve(
                        patientFullName: $record->patient?->full_name,
                        patientMrn: $record->patient?->mrn,
                    ),
                )->searchable(query: self::patientSearchQuery()),
                CurrencyColumn::make('deposited')
                    ->label(__('Deposited'))
                    ->currency(fn ($record): string => (string) ($record->currency ?? 'GHS')),
                CurrencyColumn::make('applied')
                    ->label(__('Applied'))
                    ->currency(fn ($record): string => (string) ($record->currency ?? 'GHS')),
                CurrencyColumn::make('remaining')
                    ->label(__('Remaining'))
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
                    ->options(PatientDepositStatus::class)
                    ->attribute('status'),
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->defaultSort('remaining', 'desc')
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading(__('No deposit balances'));
    }
}
