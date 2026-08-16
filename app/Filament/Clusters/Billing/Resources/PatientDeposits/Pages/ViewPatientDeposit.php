<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PatientDeposits\Pages;

use Filament\Resources\Pages\ViewRecord;
use Modules\Billing\Filament\Clusters\Billing\Resources\PatientDeposits\PatientDepositResource;
use Modules\Billing\Filament\Clusters\Billing\Resources\PatientDeposits\RelationManagers\DepositApplicationsRelationManager;

class ViewPatientDeposit extends ViewRecord
{
    protected static string $resource = PatientDepositResource::class;

    public function getRelationManagers(): array
    {
        return [
            DepositApplicationsRelationManager::class,
        ];
    }
}
