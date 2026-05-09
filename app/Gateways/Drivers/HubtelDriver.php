<?php

namespace Modules\Billing\Gateways\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Billing\Gateways\Contracts\PaymentGatewayDriver;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\PaymentIntent;

/**
 * Hubtel (Ghana) — initialize receive-money checkout; webhook verification uses POS secret from config metadata.
 */
class HubtelDriver implements PaymentGatewayDriver
{
    public function key(): string
    {
        return 'hubtel';
    }

    public function createCheckout(BranchPaymentGatewayConfig $config, PaymentIntent $intent): PaymentIntent
    {
        $clientId = $config->public_key;
        $secret = $config->secret_key;
        $posId = $config->metadata['pos_sales_id'] ?? null;
        if (! $posId) {
            $intent->update([
                'status' => PaymentIntentStatus::Failed,
                'raw_response' => ['error' => 'metadata.pos_sales_id required for Hubtel'],
            ]);

            return $intent->fresh();
        }

        $response = Http::withBasicAuth($clientId, $secret)
            ->acceptJson()
            ->post('https://payproxyapi.hubtel.com/items/initiate', [
                'totalAmount' => (float) $intent->amount,
                'description' => 'Invoice '.$intent->invoice_id,
                'callbackUrl' => $intent->metadata['callback_url'] ?? url('/'),
                'returnUrl' => $intent->metadata['success_url'] ?? url('/'),
                'cancellationUrl' => $intent->metadata['cancel_url'] ?? url('/'),
                'merchantAccountNumber' => $config->metadata['merchant_account'] ?? null,
                'clientReference' => $intent->client_reference,
            ]);

        $json = $response->json();
        if (! $response->successful() || empty($json['responseCode']) || $json['responseCode'] !== '0000') {
            $intent->update([
                'status' => PaymentIntentStatus::Failed,
                'raw_response' => $json,
            ]);

            return $intent->fresh();
        }

        $intent->update([
            'checkout_url' => $json['data']['checkoutUrl'] ?? $json['checkoutUrl'] ?? null,
            'provider_reference' => $json['data']['checkoutId'] ?? null,
            'raw_response' => $json,
        ]);

        return $intent->fresh();
    }

    public function verifyWebhookSignature(Request $request, BranchPaymentGatewayConfig $config): bool
    {
        $secret = $config->webhook_secret;
        if (! $secret) {
            return false;
        }
        $hash = $request->header('X-Hubtel-Signature') ?? $request->header('hubtel-signature');

        return $hash && hash_equals($secret, $hash);
    }

    public function parseWebhookPayload(Request $request): ?array
    {
        $ref = $request->input('ClientReference') ?? $request->input('clientReference');
        if (! $ref) {
            return null;
        }

        $status = strtolower((string) $request->input('Status', $request->input('status', '')));

        return [
            'reference' => (string) $ref,
            'amount_minor' => (int) round((float) $request->input('Amount', $request->input('amount', 0)) * 100),
            'currency' => (string) $request->input('Currency', 'GHS'),
            'success' => in_array($status, ['paid', 'success', 'successful'], true),
        ];
    }
}
