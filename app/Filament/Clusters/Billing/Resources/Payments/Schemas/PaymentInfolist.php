<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Payments\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Infolists\Components\CurrencyEntry;

class PaymentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Payment'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('client')
                            ->label(__('Client'))
                            ->state(fn ($record): string => $record->clientIdentity()->displayWithIdentifier()),
                        TextEntry::make('branch.name')->label(__('Branch')),
                        TextEntry::make('type')->badge(),
                        TextEntry::make('method')->badge(),
                        TextEntry::make('gateway'),
                        CurrencyEntry::make('amount')
                            ->currency(fn ($record) => (string) $record->currency),
                        TextEntry::make('currency'),
                        TextEntry::make('provider_transaction_id')
                            ->label(__('Transaction ID'))
                            ->copyable(),
                        TextEntry::make('received_at')->dateTime(),
                        TextEntry::make('metadata.funded_by_deposit_id')
                            ->label(__('Funded by deposit'))
                            ->visible(fn ($record) => ! empty($record->metadata['funded_by_deposit_id'] ?? null))
                            ->copyable(),
                        TextEntry::make('metadata')
                            ->json()
                            ->visible(fn ($state) => ! empty($state)),
                    ]),
            ]);
    }
}
