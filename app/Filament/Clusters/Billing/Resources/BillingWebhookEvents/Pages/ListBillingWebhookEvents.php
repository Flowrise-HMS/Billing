<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\BillingWebhookEvents\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Billing\Filament\Clusters\Billing\Resources\BillingWebhookEvents\BillingWebhookEventResource;

class ListBillingWebhookEvents extends ListRecords
{
    protected static string $resource = BillingWebhookEventResource::class;
}
