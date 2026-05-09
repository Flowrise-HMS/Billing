<?php

namespace Modules\Billing\Filament\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;

class EncounterInvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Invoices');
    }

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('invoice_number')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('invoice_number')
                    ->label(__('Invoice #'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice_type')
                    ->label(__('Type'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('total')
                    ->label(__('Total'))
                    ->numeric(decimalPlaces: 2)
                    ->sortable(),
                TextColumn::make('amount_paid')
                    ->label(__('Paid'))
                    ->numeric(decimalPlaces: 2),
                TextColumn::make('issued_at')
                    ->label(__('Issued'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make()
                    ->url(fn ($record) => InvoiceResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
