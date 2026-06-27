<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Payments;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\Pages\ListPayments;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\Pages\ViewPayment;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\RelationManagers\PaymentAllocationsRelationManager;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\Schemas\PaymentInfolist;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\Tables\PaymentsTable;
use Modules\Billing\Models\Payment;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $cluster = BillingCluster::class;

    public static function infolist(Schema $schema): Schema
    {
        return PaymentInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PaymentsTable::configure($table);
    }

    public static function getPaymentTableColumns(): array
    {
        return PaymentsTable::columns();
    }

    public static function getPaymentRecordActions(): array
    {
        return PaymentsTable::recordActions();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            PaymentAllocationsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with([
                'patient',
                'recorder',
                'allocations.invoiceLine.invoice.patient',
            ]);
    }
}
