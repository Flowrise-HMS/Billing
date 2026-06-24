<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PaymentIntents;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentIntents\Pages\ListPaymentIntents;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentIntents\Pages\ViewPaymentIntent;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentIntents\Tables\PaymentIntentsTable;
use Modules\Billing\Models\PaymentIntent;

class PaymentIntentResource extends Resource
{
    protected static ?string $model = PaymentIntent::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCreditCard;

    protected static ?string $cluster = BillingCluster::class;

    protected static ?string $recordTitleAttribute = 'client_reference';

    public static function getModelLabel(): string
    {
        return __('Payment intent');
    }

    public static function getPluralModelLabel(): string
    {
        return __('Payment intents');
    }

    public static function table(Table $table): Table
    {
        return PaymentIntentsTable::configure($table);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()
            ->where('status', PaymentIntentStatus::Pending)
            ->count();
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
