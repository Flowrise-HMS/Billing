<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PaymentIntents\Pages;

use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentIntents\PaymentIntentResource;
use Modules\Core\Filament\Infolists\Components\CurrencyEntry;

class ViewPaymentIntent extends ViewRecord
{
    protected static string $resource = PaymentIntentResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Payment Intent'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('invoice.invoice_number')
                            ->label(__('Invoice'))
                            ->url(fn ($record) => $record->invoice_id
                                ? InvoiceResource::getUrl(
                                    'view',
                                    ['record' => $record->invoice_id],
                                )
                                : null),
                        TextEntry::make('branch.name')->label(__('Branch')),
                        TextEntry::make('gateway')->badge(),
                        TextEntry::make('status')->badge(),
                        CurrencyEntry::make('amount')
                            ->currency(fn ($record) => (string) ($record->currency ?? 'GHS')),
                        TextEntry::make('currency'),
                        TextEntry::make('client_reference')->copyable(),
                        TextEntry::make('provider_reference')->copyable(),
                        TextEntry::make('line_ids')
                            ->label(__('Line IDs'))
                            ->json()
                            ->visible(fn ($state) => ! empty($state)),
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
