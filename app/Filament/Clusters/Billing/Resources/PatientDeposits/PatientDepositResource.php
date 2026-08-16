<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PatientDeposits;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\PatientDeposits\Pages\ListPatientDeposits;
use Modules\Billing\Filament\Clusters\Billing\Resources\PatientDeposits\Pages\ViewPatientDeposit;
use Modules\Billing\Filament\Clusters\Billing\Resources\PatientDeposits\Schemas\PatientDepositForm;
use Modules\Billing\Filament\Clusters\Billing\Resources\PatientDeposits\Tables\PatientDepositTable;
use Modules\Billing\Models\PatientDeposit;
use Modules\Core\Enums\NavigationGroup;

class PatientDepositResource extends Resource
{
    protected static ?string $model = PatientDeposit::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::BILLING;

    protected static ?string $cluster = BillingCluster::class;

    protected static ?string $recordTitleAttribute = 'id';

    protected static bool $canCreate = false;

    public static function form(Schema $schema): Schema
    {
        return PatientDepositForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PatientDepositTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPatientDeposits::route('/'),
            'view' => ViewPatientDeposit::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['patient', 'payment']);
    }
}
