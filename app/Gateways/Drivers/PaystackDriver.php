<?php

namespace Modules\Billing\Gateways\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Billing\Gateways\Contracts\PaymentGatewayDriver;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\PaymentIntent;

class PaystackDriver implements PaymentGatewayDriver
{
    public function key(): string
    {
        return 'paystack';
    }

    public function createCheckout(BranchPaymentGatewayConfig $config, PaymentIntent $intent): PaymentIntent
    {
        $secret = $config->secret_key;
        $email = $intent->metadata['customer_email'] ?? 'billing@example.com';

        $response = Http::withToken($secret)
            ->acceptJson()
            ->post('https://api.paystack.co/transaction/initialize', [
                'email' => $email,
                'amount' => (int) bcmul((string) $intent->amount, '100', 0),
                'currency' => strtoupper($intent->currency),
                'reference' => $intent->client_reference,
                'callback_url' => $intent->metadata['callback_url'] ?? null,
                'metadata' => [
                    'invoice_id' => $intent->invoice_id,
                    'payment_intent_id' => $intent->id,
                ],
            ]);

        $json = $response->json();
        if (! $response->successful() || empty($json['data']['authorization_url'])) {
            $intent->update([
                'status' => PaymentIntentStatus::Failed,
                'raw_response' => $json,
            ]);

            return $intent->fresh();
        }

        $intent->update([
            'checkout_url' => $json['data']['authorization_url'],
            'provider_reference' => $json['data']['access_code'] ?? null,
            'raw_response' => $json,
        ]);

        return $intent->fresh();
    }

    public function verifyWebhookSignature(Request $request, BranchPaymentGatewayConfig $config): bool
    {
        $secret = $config->webhook_secret ?? $config->secret_key;
        if (! $secret) {
            return false;
        }
        $signature = $request->header('x-paystack-signature');
        if (! $signature) {
            return false;
        }
        $computed = hash_hmac('sha512', $request->getContent(), $secret);

        return hash_equals($computed, $signature);
    }

    public function parseWebhookPayload(Request $request): ?array
    {
        $event = $request->input('event');
        $data = $request->input('data');
        if ($event !== 'charge.success' || ! is_array($data)) {
            return null;
        }

        return [
            'reference' => (string) ($data['reference'] ?? ''),
            'amount_minor' => (int) ($data['amount'] ?? 0),
            'currency' => (string) ($data['currency'] ?? 'NGN'),
            'success' => ($data['status'] ?? '') === 'success',
        ];
    }
}
