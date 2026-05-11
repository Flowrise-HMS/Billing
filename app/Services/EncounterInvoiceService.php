<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Events\InvoiceCreated;
use Modules\Billing\Models\Invoice;
use Modules\Clinical\Models\Encounter;

class EncounterInvoiceService
{
    public function __construct(
        protected InvoiceTotalsService $totalsService
    ) {}

    public function ensureDraftInvoiceForEncounter(Encounter $encounter): Invoice
    {
        $encounter->loadMissing('branch');

        $existing = Invoice::query()->withoutGlobalScopes()
            ->where('encounter_id', $encounter->id)
            ->where('status', InvoiceStatus::Draft)
            ->first();

        if ($existing) {
            return $existing;
        }

        return DB::transaction(function () use ($encounter) {
            $organizationId = $encounter->branch?->organization_id;

            Context::add('current_branch_id', $encounter->branch_id);
            try {
                $invoice = Invoice::query()->withoutGlobalScopes()->create([
                    'organization_id' => $organizationId,
                    'branch_id' => $encounter->branch_id,
                    'patient_id' => $encounter->patient_id,
                    'encounter_id' => $encounter->id,
                    'invoice_number' => Invoice::generateInvoiceNumber((string) $encounter->branch_id),
                    'status' => InvoiceStatus::Draft,
                    'invoice_type' => InvoiceType::Final,
                    'currency' => 'GHS',
                    'created_by' => auth()->id(),
                ]);

                $invoice = $invoice->fresh(['lines']);
                DB::afterCommit(fn () => Event::dispatch(new InvoiceCreated($invoice)));

                return $invoice;
            } finally {
                Context::forget('current_branch_id');
            }
        });
    }

    public function markEncounterDischarged(Encounter $encounter): void
    {
        Invoice::query()->withoutGlobalScopes()
            ->where('encounter_id', $encounter->id)
            ->where('status', InvoiceStatus::Draft)
            ->update(['encounter_discharged_at' => now()]);

        $draft = Invoice::query()->withoutGlobalScopes()
            ->where('encounter_id', $encounter->id)
            ->where('status', InvoiceStatus::Draft)
            ->first();

        if ($draft) {
            $this->totalsService->recalculate($draft);
        }
    }
}
