<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Events\InvoiceCreated;
use Modules\Billing\Models\Invoice;
use Modules\Core\Contracts\EncounterInvoiceContract;
use Modules\Core\Support\OptionalClass;

class EncounterInvoiceService implements EncounterInvoiceContract
{
    public function __construct(
        protected InvoiceTotalsService $totalsService
    ) {}

    public function ensureDraftInvoiceForEncounter(object $encounter): Invoice
    {
        return DB::transaction(function () use ($encounter) {
            if (method_exists($encounter, 'loadMissing')) {
                $encounter->loadMissing('branch');
            }

            OptionalClass::when(
                'Modules\\Clinical\\Models\\Encounter',
                function (string $encounterClass) use ($encounter): void {
                    $encounterClass::query()->withoutGlobalScopes()
                        ->where('id', $encounter->id)
                        ->lockForUpdate()
                        ->first();
                },
                'Clinical',
            );

            $existing = Invoice::query()->withoutGlobalScopes()
                ->where('encounter_id', $encounter->id)
                ->where('status', InvoiceStatus::Draft)
                ->first();

            if ($existing) {
                return $existing;
            }

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

    public function markEncounterDischarged(object $encounter): void
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
