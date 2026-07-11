<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Payments\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class PaymentAllocationsRelationManager extends RelationManager
{
    protected static string $relationship = 'allocations';

    protected static ?string $title = 'Allocations';

    public function table(Table $table): Table
    {
        $currency = fn (): string => (string) $this->getOwnerRecord()->currency;

        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['invoiceLine.invoice']))
            ->columns([
                TextColumn::make('invoiceLine.invoice.invoice_number')
                    ->label(__('Invoice')),
                TextColumn::make('invoiceLine.description')
                    ->label(__('Service'))
                    ->default('—'),
                TextColumn::make('invoiceLine.line_status')
                    ->label(__('Line status'))
                    ->badge(),
                CurrencyColumn::make('amount')
                    ->currency($currency),
            ])
            ->defaultSort('created_at');
    }
}
