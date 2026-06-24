<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PaymentIntents;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentIntents\Pages\ListPaymentIntents;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentIntents\Pages\ViewPaymentIntent;
use Modules\Billing\Models\PaymentIntent;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class PaymentIntentResource extends Resource
{
    protected static ?string $model = PaymentIntent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $cluster = BillingCluster::class;

    protected static ?string $recordTitleAttribute = 'client_reference';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice.invoice_number')
                    ->label(__('Invoice'))
                    ->searchable()
                    ->sortable(),
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
                    ->tooltip(fn (PaymentIntent $r) => $r->client_reference),
                TextColumn::make('provider_reference')
                    ->label('Provider ref')
                    ->limit(12)
                    ->copyable(),
                TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentIntents::route('/'),
            'view' => ViewPaymentIntent::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }
}
