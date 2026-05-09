<?php

namespace Modules\Billing\Listeners;

use Modules\Billing\Services\InvoiceLineSyncService;
use Modules\Clinical\Events\RequestItemUpdated;

class SyncRequestItemUpdatedToInvoice
{
    public function __construct(
        protected InvoiceLineSyncService $invoiceLineSyncService
    ) {}

    public function handle(RequestItemUpdated $event): void
    {
        $this->invoiceLineSyncService->syncFromRequestItem(
            $event->requestItem->fresh(['serviceRequest.encounter', 'service'])
        );
    }
}
