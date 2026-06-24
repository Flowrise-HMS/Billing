<?php

namespace Modules\Billing\Gateways\Drivers;

use Illuminate\Http\Request;
use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Billing\Gateways\Contracts\PaymentGatewayDriver;
use Modules\Billing\Gateways\Paystack\PaystackClientFactory;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\PaymentIntent;
use Throwable;

class PaystackDriver implements PaymentGatewayDriver
{
    public function __construct(
        protected PaystackClientFactory $clientFactory
    ) {}

    public function key(): string
    {
        return 'paystack';
    }

    public function createCheckout(BranchPaymentGatewayConfig $config, PaymentIntent $intent): PaymentIntent
    {
        $secret = $config->secret_key;
        $email = $intent->metadata['customer_email'] ?? 'billing@example.com';

        try {
            $data = $this->clientFactory->make($secret)->transaction()->initialize([
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
        } catch (Throwable $e) {
            $intent->update([
                'status' => PaymentIntentStatus::Failed,
                'raw_response' => ['error' => $e->getMessage()],
            ]);

            return $intent->fresh();
        }

        if (! is_array($data) || empty($data['authorization_url'])) {
            $intent->update([
                'status' => PaymentIntentStatus::Failed,
                'raw_response' => is_array($data) ? $data : ['response' => $data],
            ]);

            return $intent->fresh();
        }

        $intent->update([
            'checkout_url' => $data['authorization_url'],
            'provider_reference' => $data['access_code'] ?? null,
            'raw_response' => $data,
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
