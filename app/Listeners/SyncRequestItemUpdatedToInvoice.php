<?php

namespace Modules\Billing\Listeners;

use Modules\Billing\Services\InvoiceLineSyncService;
use Modules\Clinical\Events\RequestItemUpdated;
use Modules\Core\Support\AppSettings;

class SyncRequestItemUpdatedToInvoice
{
    public function __construct(
        protected InvoiceLineSyncService $invoiceLineSyncService
    ) {}

    public function handle(RequestItemUpdated $event): void
    {
        try {
            if (! app(AppSettings::class)->billing()->auto_sync_request_items) {
                return;
            }
        } catch (\Throwable) {
        }

        $this->invoiceLineSyncService->syncFromRequestItem(
            $event->requestItem->fresh(['serviceRequest.encounter', 'service'])
        );
    }
}
