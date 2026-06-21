<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages;

use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;
use pxlrbt\FilamentActivityLog\Pages\ListActivities;

class ListInvoiceActivities extends ListActivities
{
    protected static string $resource = InvoiceResource::class;
}
