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
];
