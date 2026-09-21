<?php

namespace Modules\Billing\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Modules\Billing\Services\Sms\Drivers\HubtelSmsDriver;

class BillingSmsService
{
    public function send(string $phone, string $message): void
    {
        $phone = $this->normalizePhone($phone);
        if ($phone === '' || trim($message) === '') {
            return;
        }

        $driver = (string) config('billing.notifications.sms.driver', 'log');

        match ($driver) {
            'hubtel' => app(HubtelSmsDriver::class)->send($phone, $message),
            'http' => $this->sendViaHttp($phone, $message),
            default => $this->logOnly($phone),
        };
    }

    protected function sendViaHttp(string $phone, string $message): void
    {
        $endpoint = (string) config('billing.notifications.sms.endpoint', '');
        if ($endpoint === '') {
            Log::channel('billing_sms')->warning('billing.sms.endpoint_missing', ['to' => $this->maskPhone($phone)]);

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

        Log::channel('billing_sms')->info('billing.sms.http_sent', ['to' => $this->maskPhone($phone)]);
    }

    protected function logOnly(string $phone): void
    {
        Log::channel('billing_sms')->info('billing.sms.notice', ['to' => $this->maskPhone($phone)]);
    }

    protected function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return $digits === '' ? '' : '***'.substr($digits, -4);
    }

    protected function normalizePhone(string $phone): string
    {
        return trim((string) preg_replace('/\s+/', '', $phone));
    }
}
