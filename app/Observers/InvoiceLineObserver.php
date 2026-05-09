<?php

namespace Modules\Billing\Observers;

use Illuminate\Support\Facades\Notification;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Notifications\InvoiceLineAddedNotification;
use Modules\Billing\Services\InvoiceTotalsService;
use Modules\Billing\Support\BillingNotificationRecipients;

class InvoiceLineObserver
{
    public function __construct(
        protected InvoiceTotalsService $totalsService
    ) {}

    public function saved(InvoiceLine $line): void
    {
        $wasRecentlyCreated = $line->wasRecentlyCreated;

        $this->recalculateInvoice($line);

        if ($wasRecentlyCreated) {
            $this->maybeNotifyLineAdded($line);
        }
    }

    public function deleted(InvoiceLine $line): void
    {
        if ($line->invoice) {
            $this->totalsService->recalculate($line->invoice->fresh(['lines']));
        }
    }

    protected function recalculateInvoice(InvoiceLine $line): void
    {
        $invoice = $line->invoice;
        if ($invoice) {
            $this->totalsService->recalculate($invoice->fresh(['lines']));
        }
    }

    protected function maybeNotifyLineAdded(InvoiceLine $line): void
    {
        $invoice = $line->invoice?->fresh(['patient.emergencyContacts']);

        if ($invoice === null || $invoice->status !== InvoiceStatus::Issued || ! $invoice->patient_id) {
            return;
        }

        $patient = $invoice->patient;
        if (! $patient) {
            return;
        }

        $recipients = BillingNotificationRecipients::forUnpaidInvoiceNotice($patient);

        Notification::send($recipients, new InvoiceLineAddedNotification($line->fresh(['invoice', 'service'])));
    }
}
