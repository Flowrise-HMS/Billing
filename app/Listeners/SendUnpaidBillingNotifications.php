<?php

namespace Modules\Billing\Listeners;

use Illuminate\Support\Facades\Notification;
use Modules\Billing\Events\UnpaidBillingNoticeRequired;
use Modules\Billing\Notifications\InvoiceIssuedNotification;
use Modules\Billing\Support\BillingNotificationRecipients;

class SendUnpaidBillingNotifications
{
    public function handle(UnpaidBillingNoticeRequired $event): void
    {
        $invoice = $event->invoice->loadMissing(['patient.emergencyContacts', 'branch']);

        $patient = $invoice->patient;
        if (! $patient) {
            return;
        }

        $recipients = BillingNotificationRecipients::forUnpaidInvoiceNotice($patient);

        Notification::send($recipients, new InvoiceIssuedNotification($invoice));
    }
}
