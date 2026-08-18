<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\RefundsRegister\Tables;

use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Filament\Support\SearchesPatients;
use Modules\Billing\Models\Payment;
use Modules\Core\Filament\Support\ClientIdentityColumn;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class RefundsRegisterTable
{
    use SearchesPatients;

    public static function configure(Table $table, bool $defaultToUserBranch = false): Table
    {
        return $table
            ->columns(self::columns())
            ->filters(self::filters($defaultToUserBranch), layout: FiltersLayout::AboveContentCollapsible)
            ->defaultSort('received_at', 'desc');
    }

    /**
     * @return array<int, Filter|SelectFilter>
     */
    public static function filters(bool $defaultToUserBranch = false): array
    {
        return [
            SelectFilter::make('branch_id')
                ->label(__('Branch'))
                ->relationship('branch', 'name')
                ->preload()
                ->searchable()
                ->default(fn (): ?string => $defaultToUserBranch ? Auth::user()?->branch_id : null),
            SelectFilter::make('method')
                ->label(__('Method'))
                ->options(PaymentMethod::class)
                ->attribute('method'),
            Filter::make('received_at')
                ->columns(2)
                ->columnSpan(2)
                ->schema([
                    Fieldset::make()
                        ->columnSpanFull()
                        ->label(__('Received date'))->schema([
                            DateTimePicker::make('received_from')
                                ->label(__('From'))
                                ->placeholder(__('From date'))
                                ->native(false),
                            DateTimePicker::make('received_until')
                                ->label(__('Until'))
                                ->placeholder(__('To date'))
                                ->native(false),
                        ]),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['received_from'], fn (Builder $q, $date): Builder => $q->where('received_at', '>=', $date))
                        ->when($data['received_until'], fn (Builder $q, $date): Builder => $q->where('received_at', '<=', $date));
                }),
        ];
    }

    /**
     * @return array<int, TextColumn|CurrencyColumn|ClientIdentityColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('received_at')
                ->label(__('Date'))
                ->dateTime()
                ->sortable(),
            ClientIdentityColumn::make()
                ->searchable(query: self::patientSearchQuery()),
            TextColumn::make('metadata.original_payment_id')
                ->label(__('Original payment'))
                ->placeholder(__('N/A'))
                ->searchable(),
            CurrencyColumn::make('amount')
                ->label(__('Refund amount'))
                ->currency(fn (Payment $record): string => (string) $record->currency)
                ->state(fn (Payment $record): float => abs((float) $record->amount)),
            TextColumn::make('metadata.reason')
                ->label(__('Reason'))
                ->placeholder(__('N/A'))
                ->limit(40)
                ->searchable(),
            TextColumn::make('method')
                ->badge()
                ->searchable(),
            TextColumn::make('gateway')
                ->searchable(),
            TextColumn::make('recorder.name')
                ->label(__('Recorded by'))
                ->placeholder(__('N/A')),
            TextColumn::make('allocations.invoiceLine.invoice.invoice_number')
                ->label(__('Invoice'))
                ->placeholder(__('N/A')),
        ];
    }
}
