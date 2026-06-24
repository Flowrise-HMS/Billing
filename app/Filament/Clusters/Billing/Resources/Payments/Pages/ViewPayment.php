<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Payments\Pages;

use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\PaymentResource;
use Modules\Core\Filament\Infolists\Components\CurrencyEntry;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function infolist(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                Section::make(__('Payment'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('patient.display_name')->label(__('Patient')),
                        TextEntry::make('branch.name')->label(__('Branch')),
                        TextEntry::make('method')->badge(),
                        TextEntry::make('gateway'),
                        CurrencyEntry::make('amount')
                            ->currency(fn ($record) => (string) $record->currency),
                        TextEntry::make('currency'),
                        TextEntry::make('provider_transaction_id')
                            ->label('Transaction ID')
                            ->copyable(),
                        TextEntry::make('received_at')->dateTime(),
                        TextEntry::make('metadata')
                            ->json()
                            ->visible(fn ($state) => ! empty($state)),
                    ]),
            ]);
    }

}
