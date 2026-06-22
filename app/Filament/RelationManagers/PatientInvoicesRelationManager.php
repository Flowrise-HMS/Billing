<?php

namespace Modules\Billing\Filament\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Tables\InvoicesTable;

class PatientInvoicesRelationManager extends RelationManager
{
    protected static string $relationship = 'invoices';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Invoices');
    }

    public function form(Schema $schema): Schema
    {
        return $schema;
    }

    public function table(Table $table): Table
    {
        return InvoicesTable::configure($table)
            ->recordTitleAttribute('invoice_number');
    }
}
