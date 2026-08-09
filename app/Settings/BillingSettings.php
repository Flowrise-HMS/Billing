<?php

namespace Modules\Billing\Settings;

use Spatie\LaravelSettings\Settings;

class BillingSettings extends Settings
{
    public bool $auto_issue_on_discharge = true;

    public bool $auto_sync_request_items = true;

    public bool $auto_invoice_on_checkin = true;

    public bool $financial_hold_enabled = false;

    /** @var array<int, string> */
    public array $enabled_payment_methods = ['cash', 'card', 'bank_transfer', 'mobile_money'];

    public string $default_payment_method = 'cash';

    public bool $sms_enabled = true;

    public int $overdue_reminder_cooldown_days = 7;

    public static function group(): string
    {
        return 'billing';
    }
}
