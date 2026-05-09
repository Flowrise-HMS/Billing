<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;

class ListInvoices extends ListRecords
{
    protected static string $resource = InvoiceResource::class;
}
