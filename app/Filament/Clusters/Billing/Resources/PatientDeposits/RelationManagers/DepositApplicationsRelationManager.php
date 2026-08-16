<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PatientDeposits\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class DepositApplicationsRelationManager extends RelationManager
{
    protected static string $relationship = 'applications';

    protected static ?string $title = 'Deposit applications';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                CurrencyColumn::make('amount'),
                TextColumn::make('invoice.invoice_number')
                    ->label(__('Invoice')),
                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
