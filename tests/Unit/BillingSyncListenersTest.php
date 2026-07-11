<?php

namespace Modules\Billing\Tests\Unit;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Modules\Billing\Listeners\FinalizeEncounterBilling;
use Modules\Billing\Listeners\SyncRequestItemCreatedToInvoice;
use Modules\Billing\Listeners\SyncRequestItemUpdatedToInvoice;
use Tests\TestCase;

class BillingSyncListenersTest extends TestCase
{
    public function test_billing_sync_listeners_are_queued_and_unique(): void
    {
        expect(SyncRequestItemCreatedToInvoice::class)
            ->toImplement(ShouldQueue::class)
            ->toImplement(ShouldBeUnique::class);

        expect(SyncRequestItemUpdatedToInvoice::class)
            ->toImplement(ShouldQueue::class)
            ->toImplement(ShouldBeUnique::class);

        expect(FinalizeEncounterBilling::class)
            ->toImplement(ShouldQueue::class)
            ->toImplement(ShouldBeUnique::class);
    }
}
