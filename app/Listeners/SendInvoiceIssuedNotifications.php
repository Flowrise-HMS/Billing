<?php

namespace Modules\Billing\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\Billing\Events\InvoiceIssued;
use Modules\Billing\Notifications\InvoiceIssuedNotification;
use Modules\Billing\Support\BillingNotificationRecipients;

class SendInvoiceIssuedNotifications
{
    public function handle(InvoiceIssued $event): void
    {
        $invoice = $event->invoice->loadMissing(['patient.emergencyContacts', 'branch']);

        if (bccomp($invoice->balanceDue(), '0', 2) <= 0) {
            return;
        }

        $patient = $invoice->patient;
        if (! $patient) {
            return;
        }

        $recipients = BillingNotificationRecipients::forUnpaidInvoiceNotice($patient);

        Notification::send($recipients, new InvoiceIssuedNotification($invoice));
    }
}
