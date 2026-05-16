<?php

return [
    'name' => 'Billing',
    'notifications' => [
        'sms' => [
            'driver' => env('BILLING_SMS_DRIVER', 'log'),
            'endpoint' => env('BILLING_SMS_ENDPOINT'),
            'token' => env('BILLING_SMS_TOKEN'),
            'timeout' => (int) env('BILLING_SMS_TIMEOUT', 8),
        ],
    ],
    'permissions' => [
        'view_invoice_pdf' => 'View Invoice Pdf',
        'print_invoice' => 'Print Invoice',
        'download_invoice' => 'Download Invoice',
        'print_receipt' => 'Print Receipt',
        'download_receipt' => 'Download Receipt',
    ],
];
