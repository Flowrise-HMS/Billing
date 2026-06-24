<?php

namespace Modules\Billing\Services;

use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Models\Invoice;

class PatientBalanceQueryService
{
    public function __construct(
        protected DepositBalanceService $depositBalanceService
    ) {}

    public function openBalanceForPatient(string $patientId): string
    {
        $invoices = Invoice::query()->withoutGlobalScopes()
            ->where('patient_id', $patientId)
            ->whereIn('status', [
                InvoiceStatus::Issued,
                InvoiceStatus::PartiallyPaid,
            ])
            ->get(['total', 'amount_paid']);

        $sum = '0';
        foreach ($invoices as $invoice) {
            $sum = bcadd($sum, $invoice->balanceDue(), 2);
        }

        return $sum;
    }

    public function depositBalanceForPatient(string $patientId): string
    {
        return $this->depositBalanceService->unallocatedBalanceForPatient($patientId);
    }
}
