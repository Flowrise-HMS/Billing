<?php

namespace Modules\Billing\Services;

use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Models\InvoiceLine;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Contracts\PatientFinancialHoldChecker;

class PatientFinancialHoldService implements PatientFinancialHoldChecker
{
    public function requiresFinancialHold(string $patientId, ?string $encounterId = null): bool
    {
        return InvoiceLine::query()
            ->whereHas('invoice', function ($q) use ($patientId, $encounterId) {
                $q->withoutGlobalScopes()
                    ->where('patient_id', $patientId)
                    ->where('status', InvoiceStatus::Draft);
                if ($encounterId) {
                    $q->where('encounter_id', $encounterId);
                }
            })
            ->whereIn('line_status', [InvoiceLineStatus::Unpaid, InvoiceLineStatus::Partial])
            ->where('billable_type', (new RequestItem)->getMorphClass())
            ->whereColumn('amount_paid', '<', 'line_total')
            ->whereHasMorph('billable', [RequestItem::class], function ($q) {
                $q->whereHas('service', fn ($s) => $s->where('requires_payment_before', true));
            })
            ->exists();
    }
}
