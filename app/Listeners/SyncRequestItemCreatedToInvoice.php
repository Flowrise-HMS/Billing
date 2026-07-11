<?php

namespace Modules\Billing\Listeners;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Modules\Billing\Services\InvoiceLineSyncService;
use Modules\Clinical\Events\RequestItemCreated;
use Modules\Core\Support\AppSettings;

class SyncRequestItemCreatedToInvoice implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue;

    public function __construct(
        protected InvoiceLineSyncService $invoiceLineSyncService
    ) {}

    public function uniqueId(RequestItemCreated $event): string
    {
        return 'sync-request-item-created:'.$event->requestItem->id;
    }

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
