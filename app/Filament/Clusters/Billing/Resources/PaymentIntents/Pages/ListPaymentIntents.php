<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PaymentIntents\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentIntents\PaymentIntentResource;

class ListPaymentIntents extends ListRecords
{
    protected static string $resource = PaymentIntentResource::class;
}
