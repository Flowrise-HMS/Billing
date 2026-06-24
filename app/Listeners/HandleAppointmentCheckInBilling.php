<?php

namespace Modules\Billing\Listeners;

use Illuminate\Support\Facades\DB;
use Modules\Appointment\Events\AppointmentCheckedIn;
use Modules\Appointment\Models\Appointment;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Billing\Services\InvoiceTotalsService;
use Modules\Core\Support\AppSettings;

class HandleAppointmentCheckInBilling
{
    public function __construct(
        protected InvoiceTotalsService $totalsService,
        protected InvoiceIssuanceService $issuanceService
    ) {}

    public function handle(AppointmentCheckedIn $event): void
    {
        try {
            if (! app(AppSettings::class)->billing()->auto_invoice_on_checkin) {
                return;
            }
        } catch (\Throwable) {
            return;
        }

        $appointment = Appointment::query()
            ->with('service', 'branch')
            ->find($event->appointmentId);

        if (! $appointment) {
            return;
        }

        if ($appointment->coverage_type?->isCovered() ?? false) {
            return;
        }

        if (! $appointment->service_id || ! $appointment->service) {
            return;
        }

        $service = $appointment->service;

        DB::transaction(function () use ($appointment, $service) {
            $invoice = Invoice::query()->withoutGlobalScopes()->create([
                'organization_id' => $appointment->branch?->organization_id,
                'branch_id' => $appointment->branch_id,
                'patient_id' => $appointment->patient_id,
                'appointment_id' => $appointment->id,
                'invoice_number' => Invoice::generateInvoiceNumber((string) $appointment->branch_id),
                'status' => InvoiceStatus::Draft,
                'invoice_type' => InvoiceType::Final,
                'currency' => 'GHS',
            ]);

            InvoiceLine::query()->create([
                'invoice_id' => $invoice->id,
                'billable_type' => $appointment->getMorphClass(),
                'billable_id' => $appointment->id,
                'service_id' => $service->id,
                'description' => $service->name,
                'quantity' => 1,
                'unit_price' => (string) ($service->price ?? 0),
                'line_status' => InvoiceLineStatus::Unpaid,
                'amount_paid' => 0,
            ]);

            $this->totalsService->recalculate($invoice->fresh());

            $issued = $this->issuanceService->issue($invoice->fresh(['lines']));
        });
    }
}
