<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages;

use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;
use pxlrbt\FilamentActivityLog\Pages\ListActivitiesBySubject;

class ListInvoiceActivities extends ListActivitiesBySubject
{
    protected static string $resource = InvoiceResource::class;
}
