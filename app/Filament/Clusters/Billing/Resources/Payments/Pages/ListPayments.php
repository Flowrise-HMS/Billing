<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Payments\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\PaymentResource;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;
}
