<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\BranchPaymentGatewayConfigs\Pages;

use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Modules\Billing\Filament\Clusters\Billing\Resources\BranchPaymentGatewayConfigs\BranchPaymentGatewayConfigResource;

class ListBranchPaymentGatewayConfigs extends ListRecords
{
    protected static string $resource = BranchPaymentGatewayConfigResource::class;
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
