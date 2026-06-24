<?php

namespace Modules\Billing\Listeners;

use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\EncounterInvoiceService;
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Clinical\Events\EncounterCancelled;
use Modules\Clinical\Events\EncounterFinished;
use Modules\Core\Support\AppSettings;

class FinalizeEncounterBilling
{
    public function __construct(
        protected EncounterInvoiceService $encounterInvoiceService,
        protected InvoiceIssuanceService $invoiceIssuanceService
    ) {}

    public function handle(EncounterFinished|EncounterCancelled $event): void
    {
        if ($event instanceof EncounterCancelled) {
            $this->handleCancelled($event);

            return;
        }

        try {
            if (! app(AppSettings::class)->billing()->auto_issue_on_discharge) {
                return;
            }
        } catch (\Throwable) {
            // fall through with legacy behaviour
        }

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

    public function handleCancelled(EncounterCancelled $event): void
    {
        $encounter = $event->encounter->fresh();

        Invoice::query()->withoutGlobalScopes()
            ->where('encounter_id', $encounter->id)
            ->where('status', InvoiceStatus::Draft)
            ->update(['status' => InvoiceStatus::Void]);
    }
}
