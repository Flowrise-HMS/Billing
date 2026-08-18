<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Actions\Action;
use Filament\Support\ArrayRecord;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\TextInputColumn;
use Filament\Tables\Enums\RecordActionsPosition;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Livewire\Attributes\Reactive;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class TillReconciliationTableWidget extends BaseWidget
{
    use InteractsWithWidgetShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    #[Reactive]
    public ?array $closeoutPayload = null;

    public function table(Table $table): Table
    {
        $cashiers = $this->closeoutPayload['cashiers'] ?? [];

        $rows = collect($cashiers)->values()->map(function (array $row, int $index): array {
            $row[ArrayRecord::getKeyName()] = (string) ($index);
            $row['counted_closing'] = $this->closeoutPayload['counted_closing'] ?? null;

            return $row;
        })->all();

        return $table
            ->heading(__('Till reconciliation'))
            ->records(fn (): array => $rows)
            ->columns([
                TextColumn::make('cashier_name')
                    ->label(__('Cashier')),
                CurrencyColumn::make('opening_cash')
                    ->label(__('Opening')),
                CurrencyColumn::make('cash_in')
                    ->label(__('Cash-in')),
                CurrencyColumn::make('cash_refunds')
                    ->label(__('Cash refunds')),
                CurrencyColumn::make('change_given')
                    ->label(__('Change given')),
                CurrencyColumn::make('expected_closing')
                    ->label(__('Expected closing')),
                TextInputColumn::make('counted_closing')
                    ->label(__('Counted closing'))
                    ->type('number')
                    ->step(0.01)
                    ->extraInputAttributes(fn (): array => ['min' => '0'])
                    ->updateStateUsing(function (string $state) {
                        $this->dispatch('closeout.countedClosingUpdated', countedClosing: $state);
                    }),
                CurrencyColumn::make('variance')
                    ->label(__('Variance')),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->color(fn (array $record): string => ($record['status'] ?? 'open') === 'finalized' ? 'success' : 'gray')
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
            ])
            ->actions([
                Action::make('finalize')
                    ->label(__('Finalize'))
                    ->color('success')
                    ->icon('heroicon-m-check-circle')
                    ->size('sm')
                    ->visible(fn (array $record): bool => ($record['status'] ?? 'open') === 'open')
                    ->action(function (): void {
                        $this->dispatch(
                            'closeout.finalize',
                            countedClosing: $this->closeoutPayload['counted_closing'] ?? null,
                        );
                    }),
                Action::make('reopen')
                    ->label(__('Reopen'))
                    ->color('warning')
                    ->icon('heroicon-m-arrow-uturn-left')
                    ->size('sm')
                    ->visible(fn (array $record): bool => ($record['status'] ?? 'open') === 'finalized')
                    ->action(fn () => $this->dispatch('closeout.reopen')),
            ])
            ->recordActionsPosition(RecordActionsPosition::AfterColumns)
            ->paginated(false)
            ->emptyStateHeading(__('No till data'));
    }
}
