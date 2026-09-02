<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;
use Modules\Billing\Filament\Exports\InvoiceExporter;
use Modules\Core\Filament\Support\SuperAdminExportAction;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SuperAdminExportAction::make(InvoiceExporter::class),
            CreateAction::make(),
        ];
    }
}
