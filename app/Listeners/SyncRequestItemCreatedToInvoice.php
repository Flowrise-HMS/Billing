<?php

namespace Modules\Billing\Listeners;

use Modules\Billing\Services\InvoiceLineSyncService;
use Modules\Clinical\Events\RequestItemCreated;

class SyncRequestItemCreatedToInvoice
{
    public function __construct(
        protected InvoiceLineSyncService $invoiceLineSyncService
    ) {}

    public function handle(RequestItemCreated $event): void
    {
        $this->invoiceLineSyncService->syncFromRequestItem(
            $event->requestItem->fresh(['serviceRequest.encounter', 'service'])
        );
    }
}
