<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages;

use Filament\Resources\Pages\EditRecord;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;

class EditInvoice extends EditRecord
{
    protected static string $resource = InvoiceResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['currency'] = strtoupper(substr((string) ($data['currency'] ?? $this->record->currency), 0, 3));
        $data['updated_by'] = auth()->id();

        return $data;
    }

    protected function getRedirectUrl(): string
    {
        return InvoiceResource::getUrl('view', ['record' => $this->record]);
    }
}
