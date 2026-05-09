<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\Context;
use InvalidArgumentException;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Models\Invoice;
use Modules\Core\Models\Branch;

class ManualInvoiceService
{
    public function createStandaloneDraft(
        string $branchId,
        ?string $patientId,
        string $currency = 'GHS',
        InvoiceType $invoiceType = InvoiceType::Standalone
    ): Invoice {
        $branch = Branch::query()->findOrFail($branchId);

        Context::add('current_branch_id', $branchId);
        try {
            return Invoice::query()->withoutGlobalScopes()->create([
                'organization_id' => $branch->organization_id,
                'branch_id' => $branchId,
                'patient_id' => $patientId,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'status' => InvoiceStatus::Draft,
                'invoice_type' => $invoiceType,
                'currency' => strtoupper(substr($currency, 0, 3)),
                'created_by' => auth()->id(),
            ]);
        } finally {
            Context::forget('current_branch_id');
        }
    }

    public function assertDraft(Invoice $invoice): void
    {
        if (! $invoice->isDraft()) {
            throw new InvalidArgumentException('Invoice must be in draft status.');
        }
    }
}
