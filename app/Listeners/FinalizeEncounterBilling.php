<?php

namespace Modules\Billing\Listeners;

use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\EncounterInvoiceService;
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Clinical\Events\EncounterFinished;

class FinalizeEncounterBilling
{
    public function __construct(
        protected EncounterInvoiceService $encounterInvoiceService,
        protected InvoiceIssuanceService $invoiceIssuanceService
    ) {}

    public function handle(EncounterFinished $event): void
    {
        $encounter = $event->encounter->fresh();

        $this->encounterInvoiceService->ensureDraftInvoiceForEncounter($encounter);
        $this->encounterInvoiceService->markEncounterDischarged($encounter);

        $draft = Invoice::query()->withoutGlobalScopes()
            ->where('encounter_id', $encounter->id)
            ->where('status', InvoiceStatus::Draft)
            ->first();

        if ($draft !== null && bccomp((string) $draft->total, '0', 2) > 0) {
            $this->invoiceIssuanceService->issue($draft->fresh(['lines']));
        }
    }
}
