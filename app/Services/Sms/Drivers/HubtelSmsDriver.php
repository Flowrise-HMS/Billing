<?php

namespace Modules\Billing\Services\Sms\Drivers;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Hubtel Quick SMS — POST+BasicAuth (matches Gateways\Drivers\HubtelDriver's
 * style, and keeps client credentials out of the URL/query string). Never
 * throws: a failed send is logged and degrades silently so it can never
 * break the queued notification job that triggered it.
 */
class HubtelSmsDriver
{
    public function send(string $phone, string $message): bool
    {
        $clientId = (string) config('billing.notifications.sms.hubtel.client_id', '');
        $clientSecret = (string) config('billing.notifications.sms.hubtel.client_secret', '');
        $senderId = (string) config('billing.notifications.sms.hubtel.sender_id', '');
        $endpoint = (string) config('billing.notifications.sms.hubtel.endpoint', '');
        $timeout = (int) config('billing.notifications.sms.hubtel.timeout', 8);

        if ($clientId === '' || $clientSecret === '' || $endpoint === '') {
            Log::channel('billing_sms')->warning('billing.sms.hubtel_not_configured', [
                'to' => $this->maskPhone($phone),
            ]);

            return false;
        }

        try {
            $response = Http::withBasicAuth($clientId, $clientSecret)
                ->timeout($timeout)
                ->acceptJson()
                ->post($endpoint, [
                    'From' => $senderId,
                    'To' => $phone,
                    'Content' => $message,
                ]);

            if (! $response->successful()) {
                Log::channel('billing_sms')->warning('billing.sms.hubtel_failed', [
                    'to' => $this->maskPhone($phone),
                    'status' => $response->status(),
                ]);

                return false;
            }

            Log::channel('billing_sms')->info('billing.sms.hubtel_sent', [
                'to' => $this->maskPhone($phone),
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::channel('billing_sms')->warning('billing.sms.hubtel_exception', [
                'to' => $this->maskPhone($phone),
                'error' => $e::class.': '.$e->getMessage(),
            ]);

            return false;
        }
    }

    protected function maskPhone(string $phone): string
    {
        $digits = preg_replace('/\D/', '', $phone) ?? '';

        return $digits === '' ? '' : '***'.substr($digits, -4);
    }
}
