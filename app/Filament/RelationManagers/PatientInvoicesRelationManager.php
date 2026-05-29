<?php

namespace Modules\Billing\Filament\RelationManagers;

use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Filament\Actions\RecordInvoicePaymentAction;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;
use Modules\Billing\Models\Invoice;

class PatientInvoicesRelationManager extends RelationManager
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
                TextColumn::make('balance_due')
                    ->label(__('Balance due'))
                    ->numeric(decimalPlaces: 2)
                    ->color(fn (Invoice $record): ?string => bccomp($record->balanceDue(), '0', 2) > 0 ? 'danger' : 'success')
                    ->getStateUsing(fn (Invoice $record): string => $record->balanceDue()),
                TextColumn::make('issued_at')
                    ->label(__('Issued'))
                    ->dateTime()
                    ->sortable(),
            ])
            ->recordActions([
                RecordInvoicePaymentAction::make()
                    ->arguments(fn (Invoice $record) => ['invoice_id' => $record->id])
                    ->visible(fn (Invoice $record) => ! in_array($record->status, [InvoiceStatus::Draft, InvoiceStatus::Void], true)
                        && bccomp($record->balanceDue(), '0', 2) > 0),
                ViewAction::make()
                    ->url(fn ($record) => InvoiceResource::getUrl('view', ['record' => $record])),
            ]);
    }
}
