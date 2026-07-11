<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Collection;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Models\InvoiceLine;
use Modules\Clinical\Models\RequestItem;
use Modules\Core\Contracts\PatientFinancialHoldChecker;
use Modules\Core\Support\AppSettings;

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

    /**
     * @param  Collection<int, RequestItem>  $items
     * @return array<string, bool>
     */
    public function resolveFinancialHoldsForRequestItems(Collection $items): array
    {
        if ($items->isEmpty()) {
            return [];
        }

        try {
            if (! app(AppSettings::class)->billing()->financial_hold_enabled) {
                return $items->mapWithKeys(fn (RequestItem $item): array => [(string) $item->id => false])->all();
            }
        } catch (\Throwable) {
            return $items->mapWithKeys(fn (RequestItem $item): array => [(string) $item->id => false])->all();
        }

        /** @var array<string, array{patient_id: string, encounter_id: ?string}> $contexts */
        $contexts = [];

        foreach ($items as $item) {
            $encounter = $item->serviceRequest?->encounter;
            $patientId = $encounter?->patient_id;

            if ($patientId === null) {
                continue;
            }

            $contexts[(string) $item->id] = [
                'patient_id' => (string) $patientId,
                'encounter_id' => $encounter?->id !== null ? (string) $encounter->id : null,
            ];
        }

        $flags = $items->mapWithKeys(fn (RequestItem $item): array => [(string) $item->id => false])->all();

        if ($contexts === []) {
            return $flags;
        }

        $patientIds = array_values(array_unique(array_column($contexts, 'patient_id')));

        $holdingKeys = InvoiceLine::query()
            ->whereHas('invoice', function ($q) use ($patientIds) {
                $q->withoutGlobalScopes()
                    ->whereIn('patient_id', $patientIds)
                    ->where('status', InvoiceStatus::Draft);
            })
            ->whereIn('line_status', [InvoiceLineStatus::Unpaid, InvoiceLineStatus::Partial])
            ->where('billable_type', (new RequestItem)->getMorphClass())
            ->whereColumn('amount_paid', '<', 'line_total')
            ->whereHasMorph('billable', [RequestItem::class], function ($q) {
                $q->whereHas('service', fn ($s) => $s->where('requires_payment_before', true));
            })
            ->with(['invoice:id,patient_id,encounter_id'])
            ->get(['id', 'invoice_id'])
            ->mapWithKeys(fn (InvoiceLine $line): array => [
                $this->holdKey(
                    (string) $line->invoice?->patient_id,
                    $line->invoice?->encounter_id !== null ? (string) $line->invoice->encounter_id : null,
                ) => true,
            ])
            ->all();

        foreach ($contexts as $itemId => $context) {
            $flags[$itemId] = isset($holdingKeys[$this->holdKey($context['patient_id'], $context['encounter_id'])]);
        }

        return $flags;
    }

    protected function holdKey(string $patientId, ?string $encounterId): string
    {
        return $patientId.'|'.($encounterId ?? '');
    }
}
