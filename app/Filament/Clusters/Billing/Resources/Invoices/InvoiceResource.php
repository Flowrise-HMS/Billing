<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages\CreateInvoice;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages\EditInvoice;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages\ListInvoiceActivities;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages\ListInvoices;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages\ViewInvoice;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\RelationManagers\InvoiceLinesRelationManager;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\RelationManagers\PaymentPlansRelationManager;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Schemas\InvoiceForm;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Schemas\InvoiceInfolist;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Tables\InvoicesTable;
use Modules\Billing\Models\Invoice;
use Modules\Core\Enums\NavigationGroup;

class InvoiceResource extends Resource
{
    protected static ?string $model = Invoice::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;

    protected static string|\UnitEnum|null $navigationGroup = NavigationGroup::BILLING;

    protected static ?string $cluster = BillingCluster::class;

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function form(Schema $schema): Schema
    {
        return InvoiceForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return InvoiceInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InvoicesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            InvoiceLinesRelationManager::class,
            PaymentPlansRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInvoices::route('/'),
            'create' => CreateInvoice::route('/create'),
            'edit' => EditInvoice::route('/{record}/edit'),
            'view' => ViewInvoice::route('/{record}'),
            'activities' => ListInvoiceActivities::route('/{record}/activities'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()->withoutGlobalScopes();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withoutGlobalScopes()
            ->with([
                'patient' => fn ($query) => $query->withoutGlobalScopes(),
                'branch',
            ]);
    }
}
