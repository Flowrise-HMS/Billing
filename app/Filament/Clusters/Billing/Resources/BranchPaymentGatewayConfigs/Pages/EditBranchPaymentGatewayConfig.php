<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\BranchPaymentGatewayConfigs\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Billing\Filament\Clusters\Billing\Resources\BranchPaymentGatewayConfigs\BranchPaymentGatewayConfigResource;

class EditBranchPaymentGatewayConfig extends EditRecord
{
    protected static string $resource = BranchPaymentGatewayConfigResource::class;
}
