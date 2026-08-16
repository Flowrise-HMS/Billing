<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\RefundsRegister\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Billing\Models\Payment;
use Modules\Core\Filament\Support\ClientIdentityColumn;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class RefundsRegisterTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->defaultSort('received_at', 'desc');
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
            ClientIdentityColumn::make(),
            TextColumn::make('metadata.original_payment_id')
                ->label(__('Original payment'))
                ->placeholder(__('N/A')),
            CurrencyColumn::make('amount')
                ->label(__('Refund amount'))
                ->currency(fn (Payment $record): string => (string) $record->currency)
                ->state(fn (Payment $record): float => abs((float) $record->amount)),
            TextColumn::make('metadata.reason')
                ->label(__('Reason'))
                ->placeholder(__('N/A'))
                ->limit(40),
            TextColumn::make('method')
                ->badge(),
            TextColumn::make('gateway'),
            TextColumn::make('recorder.name')
                ->label(__('Recorded by'))
                ->placeholder(__('N/A')),
            TextColumn::make('allocations.invoiceLine.invoice.invoice_number')
                ->label(__('Invoice'))
                ->placeholder(__('N/A')),
        ];
    }
}
