<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BillingSmsService
{
    public function send(string $phone, string $message): void
    {
        $phone = $this->normalizePhone($phone);
        if ($phone === '' || trim($message) === '') {
            return;
        }

        $driver = (string) config('billing.notifications.sms.driver', 'log');
        if ($driver !== 'http') {
            Log::info('billing.sms.notice', ['to' => $phone, 'message' => $message]);

            return;
        }

        $endpoint = (string) config('billing.notifications.sms.endpoint', '');
        if ($endpoint === '') {
            Log::warning('billing.sms.endpoint_missing', ['to' => $phone]);

            return;
        }

        $token = (string) config('billing.notifications.sms.token', '');
        $timeout = (int) config('billing.notifications.sms.timeout', 8);

        $request = Http::timeout($timeout)->acceptJson();
        if ($token !== '') {
            $request = $request->withToken($token);
        }

        $request->post($endpoint, [
            'to' => $phone,
            'message' => $message,
        ])->throw();
    }

    protected function normalizePhone(string $phone): string
    {
        return trim((string) preg_replace('/\s+/', '', $phone));
    }
}
