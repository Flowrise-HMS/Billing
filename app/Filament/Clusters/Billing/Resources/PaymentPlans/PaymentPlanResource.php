<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Pages\CreatePaymentPlan;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Pages\ListPaymentPlans;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Pages\ViewPaymentPlan;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Schemas\PaymentPlanForm;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Schemas\PaymentPlanInfolist;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Tables\PaymentPlansTable;
use Modules\Billing\Models\PaymentPlan;
use Modules\Core\Enums\NavigationGroup;

class PaymentPlanResource extends Resource
{
    protected static ?string $model = PaymentPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::BILLING;

    protected static ?string $cluster = BillingCluster::class;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return PaymentPlanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return PaymentPlanInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentPlansTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentPlans::route('/'),
            'create' => CreatePaymentPlan::route('/create'),
            'view' => ViewPaymentPlan::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['installments', 'invoice.patient']);
    }
}
