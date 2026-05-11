<?php

namespace Modules\Billing\Gateways\Drivers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Billing\Gateways\Contracts\PaymentGatewayDriver;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\PaymentIntent;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\Webhook;
use UnexpectedValueException;

class StripeDriver implements PaymentGatewayDriver
{
    public const STRIPE_WEBHOOK_EVENT_ATTRIBUTE = 'stripe_webhook_event';

    public function key(): string
    {
        return 'stripe';
    }

    public function createCheckout(BranchPaymentGatewayConfig $config, PaymentIntent $intent): PaymentIntent
    {
        $secret = $config->secret_key;
        $success = $intent->metadata['success_url'] ?? url('/');
        $cancel = $intent->metadata['cancel_url'] ?? url('/');

        $response = Http::withToken($secret)
            ->asForm()
            ->post('https://api.stripe.com/v1/checkout/sessions', [
                'mode' => 'payment',
                'success_url' => $success.'?session_id={CHECKOUT_SESSION_ID}',
                'cancel_url' => $cancel,
                'client_reference_id' => $intent->client_reference,
                'line_items[0][price_data][currency]' => strtolower($intent->currency),
                'line_items[0][price_data][unit_amount]' => (int) round((float) $intent->amount * 100),
                'line_items[0][price_data][product_data][name]' => 'Invoice payment',
                'line_items[0][quantity]' => 1,
            ]);

        $json = $response->json();
        if (! $response->successful() || empty($json['url'])) {
            $intent->update([
                'status' => PaymentIntentStatus::Failed,
                'raw_response' => $json,
            ]);

            return $intent->fresh();
        }

        $intent->update([
            'checkout_url' => $json['url'],
            'provider_reference' => $json['id'] ?? null,
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

        $payload = $request->getContent();
        $sigHeader = $request->header('Stripe-Signature');
        if ($payload === '' || ! $sigHeader) {
            return false;
        }

        try {
            $event = Webhook::constructEvent($payload, $sigHeader, $secret, 300);
        } catch (UnexpectedValueException|SignatureVerificationException) {
            return false;
        }

        $request->attributes->set(self::STRIPE_WEBHOOK_EVENT_ATTRIBUTE, $event);

        return true;
    }

    public function parseWebhookPayload(Request $request): ?array
    {
        $event = $request->attributes->get(self::STRIPE_WEBHOOK_EVENT_ATTRIBUTE);
        if ($event instanceof Event) {
            if ($event->type !== 'checkout.session.completed') {
                return null;
            }
            $obj = $event->data->object;
            if (! is_object($obj)) {
                return null;
            }

            return [
                'reference' => (string) ($obj->client_reference_id ?? ''),
                'amount_minor' => (int) ($obj->amount_total ?? 0),
                'currency' => strtoupper((string) ($obj->currency ?? 'usd')),
                'success' => ($obj->payment_status ?? '') === 'paid',
            ];
        }

        $type = $request->input('type');
        $obj = $request->input('data.object');
        if ($type !== 'checkout.session.completed' || ! is_array($obj)) {
            return null;
        }

        return [
            'reference' => (string) ($obj['client_reference_id'] ?? ''),
            'amount_minor' => (int) ($obj['amount_total'] ?? 0),
            'currency' => strtoupper((string) ($obj['currency'] ?? 'usd')),
            'success' => ($obj['payment_status'] ?? '') === 'paid',
        ];
    }
}
