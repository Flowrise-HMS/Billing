<?php

namespace Modules\Billing\Gateways\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Billing\Gateways\Contracts\PaymentGatewayDriver;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\PaymentIntent;

class FlutterwaveDriver implements PaymentGatewayDriver
{
    public function key(): string
    {
        return 'flutterwave';
    }

    public function createCheckout(BranchPaymentGatewayConfig $config, PaymentIntent $intent): PaymentIntent
    {
        $secret = $config->secret_key;
        $response = Http::withToken($secret)
            ->acceptJson()
            ->post('https://api.flutterwave.com/v3/payments', [
                'tx_ref' => $intent->client_reference,
                'amount' => (string) $intent->amount,
                'currency' => strtoupper($intent->currency),
                'redirect_url' => $intent->metadata['callback_url'] ?? url('/'),
                'customer' => [
                    'email' => $intent->metadata['customer_email'] ?? 'billing@example.com',
                ],
                'meta' => [
                    'invoice_id' => $intent->invoice_id,
                ],
            ]);

        $json = $response->json();
        if (! $response->successful() || empty($json['data']['link'])) {
            $intent->update([
                'status' => PaymentIntentStatus::Failed,
                'raw_response' => $json,
            ]);

            return $intent->fresh();
        }

        $intent->update([
            'checkout_url' => $json['data']['link'],
            'provider_reference' => (string) ($json['data']['id'] ?? ''),
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
        $signature = $request->header('verif-hash');

        return $signature && hash_equals($secret, $signature);
    }

    public function parseWebhookPayload(Request $request): ?array
    {
        $status = $request->input('data.status');
        $txRef = $request->input('data.tx_ref');
        if (! $txRef || strtolower((string) $status) !== 'successful') {
            return null;
        }

        return [
            'reference' => (string) $txRef,
            'amount_minor' => (int) round((float) $request->input('data.amount', 0) * 100),
            'currency' => (string) $request->input('data.currency', 'NGN'),
            'success' => true,
        ];
    }
}
