<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PaymentIntents\Pages;

use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentIntents\PaymentIntentResource;

class ViewPaymentIntent extends ViewRecord
{
    protected static string $resource = PaymentIntentResource::class;

    public function infolist(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return $schema
            ->components([
                Section::make(__('Payment Intent'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('invoice.invoice_number')->label(__('Invoice')),
                        TextEntry::make('branch.name')->label(__('Branch')),
                        TextEntry::make('gateway')->badge(),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('amount'),
                        TextEntry::make('currency'),
                        TextEntry::make('client_reference')->copyable(),
                        TextEntry::make('provider_reference')->copyable(),
                        TextEntry::make('checkout_url')->copyable()->limit(50),
                        TextEntry::make('expires_at')->dateTime(),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('raw_response')
                            ->json()
                            ->visible(fn ($state) => ! empty($state)),
                        TextEntry::make('metadata')
                            ->json()
                            ->visible(fn ($state) => ! empty($state)),
                    ]),
            ]);
    }
}
