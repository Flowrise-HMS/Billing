<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Modules\Appointment\Database\Factories\AppointmentFactory;
use Modules\Appointment\Enums\AppointmentStatus;
use Modules\Appointment\Events\AppointmentCheckedIn;
use Modules\Appointment\Models\Appointment;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Models\Invoice;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Core\Database\Factories\ServiceFactory;
use Modules\Core\Enums\CoverageType;
use Modules\Core\Settings\BillingSettings;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class AppointmentCheckInBillingTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Appointment', 'Billing']);
    }

    public function test_check_in_creates_invoice_when_auto_invoice_on_checkin_enabled(): void
    {
        $this->setAutoInvoiceOnCheckin(true);

        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $service = ServiceFactory::new()->create([
            'branch_id' => $branch->id,
            'price' => 75,
        ]);

        $appointment = Appointment::withoutEvents(
            fn () => AppointmentFactory::new()->create([
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'service_id' => $service->id,
                'coverage_type' => CoverageType::NONE,
                'status' => AppointmentStatus::ARRIVED,
            ])
        );

        event(new AppointmentCheckedIn($appointment->id));

        $invoice = Invoice::query()->withoutGlobalScopes()
            ->where('appointment_id', $appointment->id)
            ->first();

        $this->assertNotNull($invoice);
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);
    }

    public function test_check_in_skips_invoice_when_auto_invoice_on_checkin_disabled(): void
    {
        $this->setAutoInvoiceOnCheckin(false);

        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $service = ServiceFactory::new()->create([
            'branch_id' => $branch->id,
            'price' => 75,
        ]);

        $appointment = Appointment::withoutEvents(
            fn () => AppointmentFactory::new()->create([
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'service_id' => $service->id,
                'coverage_type' => CoverageType::NONE,
                'status' => AppointmentStatus::ARRIVED,
            ])
        );

        event(new AppointmentCheckedIn($appointment->id));

        $this->assertNull(
            Invoice::query()->withoutGlobalScopes()->where('appointment_id', $appointment->id)->first()
        );
    }

    protected function setAutoInvoiceOnCheckin(bool $enabled): void
    {
        $settings = app(BillingSettings::class);
        $settings->auto_invoice_on_checkin = $enabled;
        $settings->save();
    }
}
