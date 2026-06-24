<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PaymentIntents\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;
use Modules\Billing\Models\PaymentIntent;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class PaymentIntentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice.invoice_number')
                    ->label(__('Invoice'))
                    ->searchable()
                    ->sortable()
                    ->url(fn (PaymentIntent $record) => $record->invoice_id
                        ? InvoiceResource::getUrl(
                            'view',
                            ['record' => $record->invoice_id],
                        )
                        : null),
                TextColumn::make('branch.name')
                    ->label(__('Branch'))
                    ->sortable(),
                TextColumn::make('gateway')
                    ->badge(),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                CurrencyColumn::make('amount')
                    ->currency(fn (PaymentIntent $record): string => (string) $record->currency),
                TextColumn::make('currency'),
                TextColumn::make('client_reference')
                    ->label('Ref')
                    ->limit(12)
                    ->copyable()
                    ->searchable()
                    ->tooltip(fn (PaymentIntent $r) => $r->client_reference),
                TextColumn::make('provider_reference')
                    ->label('Provider ref')
                    ->limit(12)
                    ->copyable()
                    ->searchable(),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(PaymentIntentStatus::class)
                    ->multiple(),
                SelectFilter::make('gateway')
                    ->label(__('Gateway'))
                    ->options(fn (): array => PaymentIntent::query()
                        ->distinct()
                        ->pluck('gateway', 'gateway')
                        ->toArray()
                    )
                    ->multiple(),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchable();
    }
}
