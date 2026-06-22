<?php

namespace Modules\Billing\Listeners;

use Modules\Billing\Services\InvoiceLineSyncService;
use Modules\Clinical\Events\RequestItemCreated;
use Modules\Core\Support\AppSettings;

class SyncRequestItemCreatedToInvoice
{
    public function __construct(
        protected InvoiceLineSyncService $invoiceLineSyncService
    ) {}

    public function handle(RequestItemCreated $event): void
    {
        try {
            if (! app(AppSettings::class)->billing()->auto_sync_request_items) {
                return;
            }
        } catch (\Throwable) {
            // fall through
        }

        $this->invoiceLineSyncService->syncFromRequestItem(
            $event->requestItem->fresh(['serviceRequest.encounter', 'service'])
        );
    }
}
