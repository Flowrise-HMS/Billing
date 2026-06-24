<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\BillingWebhookEvents;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\BillingWebhookEvents\Pages\ListBillingWebhookEvents;
use Modules\Billing\Filament\Clusters\Billing\Resources\BillingWebhookEvents\Pages\ViewBillingWebhookEvent;
use Modules\Billing\Filament\Clusters\Billing\Resources\BillingWebhookEvents\Tables\BillingWebhookEventsTable;
use Modules\Billing\Models\BillingWebhookEvent;

class BillingWebhookEventResource extends Resource
{
    protected static ?string $model = BillingWebhookEvent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowRightOnRectangle;

    protected static ?string $cluster = BillingCluster::class;

    protected static ?string $recordTitleAttribute = 'idempotency_key';

    public static function getModelLabel(): string
    {
        return __('Webhook event');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Webhook events');
    }

    public static function table(Table $table): Table
    {
        return BillingWebhookEventsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()
            ->whereNull('processed_at')
            ->count();
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
