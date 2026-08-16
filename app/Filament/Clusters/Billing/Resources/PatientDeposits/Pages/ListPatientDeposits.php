<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PatientDeposits\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Billing\Filament\Actions\RecordDepositAction;
use Modules\Billing\Filament\Clusters\Billing\Resources\PatientDeposits\PatientDepositResource;

class ListPatientDeposits extends ListRecords
{
    protected static string $resource = PatientDepositResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RecordDepositAction::make(),
        ];
    }
}
