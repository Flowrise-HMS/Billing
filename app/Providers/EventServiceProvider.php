<?php

namespace Modules\Billing\Providers;

use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;
use Modules\Billing\Events\InvoiceIssued;
use Modules\Billing\Events\UnpaidBillingNoticeRequired;
use Modules\Billing\Listeners\FinalizeEncounterBilling;
use Modules\Billing\Listeners\HandleAppointmentCheckInBilling;
use Modules\Billing\Listeners\SendInvoiceIssuedNotifications;
use Modules\Billing\Listeners\SendUnpaidBillingNotifications;
use Modules\Billing\Listeners\SyncRequestItemCreatedToInvoice;
use Modules\Billing\Listeners\SyncRequestItemUpdatedToInvoice;
use Modules\Core\Support\ModuleAvailability;

class EventServiceProvider extends ServiceProvider
{
    /**
     * @var array<class-string, array<int, class-string>>
     */
    protected $listen = [];

    protected static $shouldDiscoverEvents = false;

    /**
     * @return array<class-string, array<int, class-string>>
     */
    public function listens(): array
    {
        $listen = [
            InvoiceIssued::class => [
                SendInvoiceIssuedNotifications::class,
            ],
            UnpaidBillingNoticeRequired::class => [
                SendUnpaidBillingNotifications::class,
            ],
        ];

        if (ModuleAvailability::clinicalEnabled()) {
            $clinicalMap = [
                'Modules\\Clinical\\Events\\RequestItemCreated' => SyncRequestItemCreatedToInvoice::class,
                'Modules\\Clinical\\Events\\RequestItemUpdated' => SyncRequestItemUpdatedToInvoice::class,
                'Modules\\Clinical\\Events\\EncounterFinished' => FinalizeEncounterBilling::class,
                'Modules\\Clinical\\Events\\EncounterCancelled' => FinalizeEncounterBilling::class,
            ];

            foreach ($clinicalMap as $event => $listener) {
                if (class_exists($event) && class_exists($listener)) {
                    $listen[$event] = [$listener];
                }
            }
        }

        if (ModuleAvailability::appointmentEnabled()) {
            $appointmentEvent = 'Modules\\Appointment\\Events\\AppointmentCheckedIn';

            if (class_exists($appointmentEvent) && class_exists(HandleAppointmentCheckInBilling::class)) {
                $listen[$appointmentEvent] = [
                    HandleAppointmentCheckInBilling::class,
                ];
            }
        }

        return $listen;
    }

    protected function configureEmailVerification(): void {}
}
