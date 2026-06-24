<?php

namespace Modules\Billing\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Appointment\Events\AppointmentCheckedIn;
use Modules\Billing\Events\UnpaidBillingNoticeRequired;
use Modules\Billing\Listeners\FinalizeEncounterBilling;
use Modules\Billing\Listeners\HandleAppointmentCheckInBilling;
use Modules\Billing\Listeners\SendUnpaidBillingNotifications;
use Modules\Billing\Listeners\SyncRequestItemCreatedToInvoice;
use Modules\Billing\Listeners\SyncRequestItemUpdatedToInvoice;
use Modules\Clinical\Events\EncounterCancelled;
use Modules\Clinical\Events\EncounterFinished;
use Modules\Clinical\Events\RequestItemCreated;
use Modules\Clinical\Events\RequestItemUpdated;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [
        RequestItemCreated::class => [
            SyncRequestItemCreatedToInvoice::class,
        ],
        RequestItemUpdated::class => [
            SyncRequestItemUpdatedToInvoice::class,
        ],
        EncounterFinished::class => [
            FinalizeEncounterBilling::class,
        ],
        EncounterCancelled::class => [
            FinalizeEncounterBilling::class,
        ],
        AppointmentCheckedIn::class => [
            HandleAppointmentCheckInBilling::class,
        ],
        UnpaidBillingNoticeRequired::class => [
            SendUnpaidBillingNotifications::class,
        ],
    ];

    protected static $shouldDiscoverEvents = false;

    protected function configureEmailVerification(): void {}
}
