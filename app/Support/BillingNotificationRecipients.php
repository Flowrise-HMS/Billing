<?php

namespace Modules\Billing\Support;

use Modules\Patient\Models\EmergencyContact;
use Modules\Patient\Models\Patient;

class BillingNotificationRecipients
{
    /**
     * Patient + emergency contacts selected for billing notices (notify_for_billing, else primary).
     *
     * @return array<int, Patient|EmergencyContact>
     */
    public static function forUnpaidInvoiceNotice(Patient $patient): array
    {
        $patient->loadMissing('emergencyContacts');

        $contacts = $patient->emergencyContacts;

        $targets = $contacts->where('notify_for_billing', true);
        if ($targets->isEmpty()) {
            $targets = $contacts->where('is_primary', true);
        }

        return array_merge([$patient], $targets->all());
    }
}
