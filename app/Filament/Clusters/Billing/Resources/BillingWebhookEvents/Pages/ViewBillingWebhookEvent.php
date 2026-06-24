<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\BillingWebhookEvents\Pages;

use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Billing\Filament\Clusters\Billing\Resources\BillingWebhookEvents\BillingWebhookEventResource;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\PaymentResource;

class ViewBillingWebhookEvent extends ViewRecord
{
    protected static string $resource = BillingWebhookEventResource::class;

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Webhook Event'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('driver')->badge(),
                        TextEntry::make('idempotency_key')->copyable(),
                        TextEntry::make('payment.id')
                            ->label(__('Payment ID'))
                            ->url(fn ($record) => $record->payment_id
                                ? PaymentResource::getUrl(
                                    'view',
                                    ['record' => $record->payment_id],
                                )
                                : null),
                        TextEntry::make('processed_at')
                            ->dateTime()
                            ->badge()
                            ->color(fn ($state) => $state ? 'success' : 'warning')
                            ->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-clock')
                            ->formatStateUsing(fn ($state) => $state
                                ? __('Processed at :date', ['date' => $state->format('Y-m-d H:i')])
                                : __('Pending')),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('metadata')
                            ->json()
                            ->visible(fn ($state) => ! empty($state)),
                    ]),
            ]);
    }
}
