<?php

return [
    'name' => 'Billing',
    'notifications' => [
        'sms' => [
            'driver' => env('BILLING_SMS_DRIVER', 'log'),
            'endpoint' => env('BILLING_SMS_ENDPOINT'),
            'token' => env('BILLING_SMS_TOKEN'),
            'timeout' => (int) env('BILLING_SMS_TIMEOUT', 8),
            'hubtel' => [
                'client_id' => env('BILLING_SMS_HUBTEL_CLIENT_ID'),
                'client_secret' => env('BILLING_SMS_HUBTEL_CLIENT_SECRET'),
                'sender_id' => env('BILLING_SMS_HUBTEL_SENDER_ID'),
                'endpoint' => env('BILLING_SMS_HUBTEL_ENDPOINT', 'https://sms.hubtel.com/v1/messages/send'),
                'timeout' => (int) env('BILLING_SMS_HUBTEL_TIMEOUT', 8),
            ],
        ],
    ],

    'public_pay_link' => [
        'ttl_days' => (int) env('BILLING_PUBLIC_PAY_LINK_TTL_DAYS', 7),
        'rate_limit_per_minute' => (int) env('BILLING_PUBLIC_PAY_LINK_RATE_LIMIT', 10),
    ],
    'permissions' => [
        'view_invoice_pdf' => 'View Invoice Pdf',
        'print_invoice' => 'Print Invoice',
        'download_invoice' => 'Download Invoice',
        'print_receipt' => 'Print Receipt',
        'download_receipt' => 'Download Receipt',
        'manage_billing_settings' => 'ManageBillingSettings',
    ],
];
