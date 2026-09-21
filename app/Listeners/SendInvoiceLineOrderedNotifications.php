<?php

namespace Modules\Billing\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Events\InvoiceLineAdded;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Notifications\InvoiceLineOrderedNotification;
use Modules\Billing\Support\BillingNotificationRecipients;
use Modules\Core\Enums\ServiceCategoryCode;

/**
 * Notifies the patient the moment an order (lab, medication, service) is
 * billed, even while the encounter invoice is still Draft. Intentionally
 * bypassed for medication orders — those auto-issue their invoice right
 * after this event fires (InvoiceLineSyncService::issueDraftInvoiceForMedicationItem)
 * and already get InvoiceIssuedNotification with a whole-invoice pay link;
 * sending both would double-notify the patient for the same order.
 */
class SendInvoiceLineOrderedNotifications
{
    public function handle(InvoiceLineAdded $event): void
    {
        $line = $event->line->fresh(['invoice.patient.emergencyContacts', 'service.category', 'billable.prescriptionDetail']);

        if ($line === null || $line->line_status === InvoiceLineStatus::Void) {
            return;
        }

        if (bccomp($line->remainingAmount(), '0', 2) <= 0) {
            return;
        }

        $patient = $line->invoice?->patient;

        if (! $patient) {
            return;
        }

        if ($this->isMedicationOrder($line)) {
            return;
        }

        $recipients = BillingNotificationRecipients::forUnpaidInvoiceNotice($patient);

        Notification::send($recipients, new InvoiceLineOrderedNotification($line));
    }

    /**
     * Mirrors InvoiceLineSyncService::isMedicationRequestItem(), checked
     * directly on the billable request item rather than the invoice status
     * (which can't be trusted yet — see class docblock).
     */
    protected function isMedicationOrder(InvoiceLine $line): bool
    {
        $billable = $line->billable;

        if ($billable !== null && method_exists($billable, 'prescriptionDetail') && $billable->prescriptionDetail !== null) {
            return true;
        }

        $categoryCode = $line->service?->category?->code;

        return $categoryCode === ServiceCategoryCode::MED || $categoryCode === ServiceCategoryCode::PHA;
    }
}
