<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\RefundsRegister\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Billing\Filament\Clusters\Billing\Resources\RefundsRegister\RefundsRegisterResource;

class ListRefunds extends ListRecords
{
    protected static string $resource = RefundsRegisterResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
