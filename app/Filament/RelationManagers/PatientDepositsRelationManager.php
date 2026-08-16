<?php

namespace Modules\Billing\Filament\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class PatientDepositsRelationManager extends RelationManager
{
    protected static string $relationship = 'deposits';

    protected static ?string $title = 'Deposits & Balance';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Deposits & Balance');
    }

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                CurrencyColumn::make('amount')
                    ->currency(fn ($record): string => $record->currency ?? 'GHS'),
                TextColumn::make('payment.method')
                    ->label(__('Method')),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('unallocated_balance')
                    ->label(__('Available'))
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state, 2)),
                TextColumn::make('created_at')
                    ->dateTime(),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
