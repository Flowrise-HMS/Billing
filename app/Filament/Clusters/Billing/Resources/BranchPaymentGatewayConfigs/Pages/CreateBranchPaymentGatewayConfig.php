<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\BranchPaymentGatewayConfigs\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Billing\Filament\Clusters\Billing\Resources\BranchPaymentGatewayConfigs\BranchPaymentGatewayConfigResource;

class CreateBranchPaymentGatewayConfig extends CreateRecord
{
    protected static string $resource = BranchPaymentGatewayConfigResource::class;
}
