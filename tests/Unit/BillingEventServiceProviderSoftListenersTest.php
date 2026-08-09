<?php

namespace Modules\Billing\Tests\Unit;

use Modules\Billing\Listeners\FinalizeEncounterBilling;
use Modules\Billing\Listeners\HandleAppointmentCheckInBilling;
use Modules\Billing\Listeners\SyncRequestItemCreatedToInvoice;
use Modules\Billing\Listeners\SyncRequestItemUpdatedToInvoice;
use Modules\Billing\Providers\EventServiceProvider;
use Modules\Core\Support\ModuleAvailability;
use Nwidart\Modules\Facades\Module;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BillingEventServiceProviderSoftListenersTest extends TestCase
{
    #[Test]
    public function it_registers_clinical_and_appointment_listeners_when_modules_enabled(): void
    {
        $this->requireModule('Clinical');
        $this->requireModule('Appointment');

        $listen = (new EventServiceProvider($this->app))->listens();

        $this->assertSame(
            [SyncRequestItemCreatedToInvoice::class],
            $listen['Modules\\Clinical\\Events\\RequestItemCreated'] ?? null,
        );
        $this->assertSame(
            [SyncRequestItemUpdatedToInvoice::class],
            $listen['Modules\\Clinical\\Events\\RequestItemUpdated'] ?? null,
        );
        $this->assertSame(
            [FinalizeEncounterBilling::class],
            $listen['Modules\\Clinical\\Events\\EncounterFinished'] ?? null,
        );
        $this->assertSame(
            [FinalizeEncounterBilling::class],
            $listen['Modules\\Clinical\\Events\\EncounterCancelled'] ?? null,
        );
        $this->assertSame(
            [HandleAppointmentCheckInBilling::class],
            $listen['Modules\\Appointment\\Events\\AppointmentCheckedIn'] ?? null,
        );
    }

    #[Test]
    public function it_skips_clinical_listeners_when_clinical_module_is_disabled(): void
    {
        $this->requireModule('Clinical');

        $module = Module::find('Clinical');
        $this->assertNotNull($module);

        try {
            $module->disable();
            $this->assertFalse(ModuleAvailability::clinicalEnabled());

            $listen = (new EventServiceProvider($this->app))->listens();

            $this->assertArrayNotHasKey('Modules\\Clinical\\Events\\RequestItemCreated', $listen);
            $this->assertArrayNotHasKey('Modules\\Clinical\\Events\\RequestItemUpdated', $listen);
            $this->assertArrayNotHasKey('Modules\\Clinical\\Events\\EncounterFinished', $listen);
            $this->assertArrayNotHasKey('Modules\\Clinical\\Events\\EncounterCancelled', $listen);
        } finally {
            $module->enable();
        }
    }

    #[Test]
    public function it_skips_appointment_listeners_when_appointment_module_is_disabled(): void
    {
        $this->requireModule('Appointment');

        $module = Module::find('Appointment');
        $this->assertNotNull($module);

        try {
            $module->disable();
            $this->assertFalse(ModuleAvailability::appointmentEnabled());

            $listen = (new EventServiceProvider($this->app))->listens();

            $this->assertArrayNotHasKey('Modules\\Appointment\\Events\\AppointmentCheckedIn', $listen);
        } finally {
            $module->enable();
        }
    }
}
