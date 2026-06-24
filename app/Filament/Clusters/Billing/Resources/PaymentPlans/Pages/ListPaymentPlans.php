<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\PaymentPlanResource;

class ListPaymentPlans extends ListRecords
{
    protected static string $resource = PaymentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
