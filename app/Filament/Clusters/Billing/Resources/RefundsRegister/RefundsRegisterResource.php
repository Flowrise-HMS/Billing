<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\RefundsRegister;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\RefundsRegister\Pages\ListRefunds;
use Modules\Billing\Filament\Clusters\Billing\Resources\RefundsRegister\Tables\RefundsRegisterTable;
use Modules\Billing\Models\Payment;
use Modules\Core\Enums\NavigationGroup;

class RefundsRegisterResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnLeft;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::BILLING;

    protected static ?string $cluster = BillingCluster::class;

    protected static bool $canCreate = false;

    public static function table(Table $table): Table
    {
        return RefundsRegisterTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRefunds::route('/'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('type', PaymentType::Refund)
            ->with(['patient', 'recorder', 'allocations.invoiceLine.invoice.patient']);
    }
}
