<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\BillingWebhookEvents;

use BackedEnum;
use Filament\Resources\Pages\Page;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\BillingWebhookEvents\Pages\ListBillingWebhookEvents;
use Modules\Billing\Filament\Clusters\Billing\Resources\BillingWebhookEvents\Pages\ViewBillingWebhookEvent;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\PaymentResource;
use Modules\Billing\Models\BillingWebhookEvent;

class BillingWebhookEventResource extends Resource
{
    protected static ?string $model = BillingWebhookEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowRightOnRectangle;

    protected static ?string $cluster = BillingCluster::class;

    protected static ?string $recordTitleAttribute = 'idempotency_key';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('driver')
                    ->badge()
                    ->sortable(),
                TextColumn::make('idempotency_key')
                    ->label('Idempotency key')
                    ->limit(20)
                    ->copyable()
                    ->searchable(),
                TextColumn::make('payment.id')
                    ->label('Payment')
                    ->formatStateUsing(fn ($state) => $state ? substr((string) $state, 0, 8).'…' : '—')
                    ->url(fn (BillingWebhookEvent $record) => $record->payment_id
                        ? Page::getResourceUrl(
                            PaymentResource::class,
                            ['record' => $record->payment_id],
                            'view',
                        )
                        : null),
                TextColumn::make('processed_at')
                    ->dateTime()
                    ->sortable()
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-clock'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListBillingWebhookEvents::route('/'),
            'view' => ViewBillingWebhookEvent::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScopes();
    }
}
