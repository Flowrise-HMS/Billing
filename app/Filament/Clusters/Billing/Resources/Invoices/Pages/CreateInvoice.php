<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages;

use Filament\Resources\Pages\CreateRecord;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;
use Modules\Billing\Models\Invoice;
use Modules\Core\Classes\Services\BranchService;
use Modules\Core\Models\Branch;

class CreateInvoice extends CreateRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $branchId = app(BranchService::class)->getDefaultBranchId();
        if (! $branchId) {
            abort(403, __('No branch context.'));
        }

        $branch = Branch::query()->findOrFail($branchId);

        $data['branch_id'] = $branchId;
        $data['organization_id'] = $branch->organization_id;
        $data['invoice_number'] = Invoice::generateInvoiceNumber((string) $branchId);
        $data['status'] = InvoiceStatus::Draft->value;
        $data['invoice_type'] = $data['invoice_type'] ?? InvoiceType::Standalone->value;
        $data['currency'] = strtoupper(substr((string) ($data['currency'] ?? 'GHS'), 0, 3));
        $data['created_by'] = auth()->id();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->redirect(InvoiceResource::getUrl('edit', ['record' => $this->record]));
    }
}
