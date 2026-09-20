<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
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

    /**
     * Lowest sort in the cluster (others default to -1), so the Billing group
     * comes first and /billing opens on Invoices.
     */
    protected static ?int $navigationSort = -10;

    protected static ?string $recordTitleAttribute = 'invoice_number';

    public static function getGloballySearchableAttributes(): array
    {
        return ['invoice_number', 'patient.mrn', 'patient.first_name', 'patient.middle_name', 'patient.last_name'];
    }

    public static function getGlobalSearchResultDetails(Model $record): array
    {
        return array_filter([
            'Patient' => $record->patient?->full_name,
            'Status' => $record->status?->getLabel(),
            'Total' => $record->currency !== null ? "{$record->currency} {$record->total}" : $record->total,
            'Issued' => $record->issued_at?->format('d M Y'),
        ]);
    }

    public static function getGlobalSearchEloquentQuery(): Builder
    {
        return parent::getGlobalSearchEloquentQuery()->with('patient');
    }

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
