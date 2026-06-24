<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\BillingWebhookEvents\Pages;

use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Modules\Billing\Filament\Clusters\Billing\Resources\BillingWebhookEvents\BillingWebhookEventResource;

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
                        TextEntry::make('payment.id')->label(__('Payment ID')),
                        TextEntry::make('processed_at')->dateTime(),
                        TextEntry::make('created_at')->dateTime(),
                        TextEntry::make('metadata')
                            ->json()
                            ->visible(fn ($state) => ! empty($state)),
                    ]),
            ]);
    }
}
