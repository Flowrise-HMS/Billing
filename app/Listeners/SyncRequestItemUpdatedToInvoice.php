<?php

namespace Modules\Billing\Listeners;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Billing\Services\InvoiceLineSyncService;
use Modules\Clinical\Events\RequestItemUpdated;
use Modules\Core\Support\AppSettings;

class SyncRequestItemUpdatedToInvoice implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected InvoiceLineSyncService $invoiceLineSyncService
    ) {}

    public function uniqueId(RequestItemUpdated $event): string
    {
        return 'sync-request-item-updated:'.$event->requestItem->id;
    }

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
