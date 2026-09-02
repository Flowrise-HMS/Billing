<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Payments\Pages;

use Filament\Resources\Pages\ListRecords;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\PaymentResource;
use Modules\Billing\Filament\Exports\PaymentExporter;
use Modules\Core\Filament\Support\SuperAdminExportAction;

class ListPayments extends ListRecords
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            SuperAdminExportAction::make(PaymentExporter::class),
        ];
    }
}
