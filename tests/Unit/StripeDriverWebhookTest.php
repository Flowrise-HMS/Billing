<?php

namespace Modules\Billing\Tests\Unit;

use Illuminate\Http\Request;
use Modules\Billing\Gateways\Drivers\StripeDriver;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Tests\TestCase;

class StripeDriverWebhookTest extends TestCase
{
    public function test_it_rejects_invalid_stripe_signature(): void
    {
        $config = new BranchPaymentGatewayConfig([
            'webhook_secret' => 'whsec_testsecret',
        ]);

        $payload = '{"type":"checkout.session.completed","data":{"object":{"client_reference_id":"ref-1","amount_total":1000,"currency":"usd","payment_status":"paid"}}}';
        $request = Request::create('/hook', 'POST', [], [], [], [], $payload);
        $request->headers->set('Stripe-Signature', 't='.time().',v1=invalid');

        $driver = new StripeDriver;

        $this->assertFalse($driver->verifyWebhookSignature($request, $config));
    }

    public function test_it_accepts_valid_stripe_signature_and_stashes_event(): void
    {
        $secret = 'whsec_testsecret';
        $config = new BranchPaymentGatewayConfig([
            'webhook_secret' => $secret,
        ]);

        $payload = '{"id":"evt_test","type":"checkout.session.completed","data":{"object":{"client_reference_id":"ref-1","amount_total":1000,"currency":"usd","payment_status":"paid"}}}';
        $timestamp = time();
        $signedPayload = $timestamp.'.'.$payload;
        $signature = hash_hmac('sha256', $signedPayload, $secret);
        $header = 't='.$timestamp.',v1='.$signature;

        $request = Request::create('/hook', 'POST', [], [], [], [], $payload);
        $request->headers->set('Stripe-Signature', $header);

        $driver = new StripeDriver;

        $this->assertTrue($driver->verifyWebhookSignature($request, $config));

        $parsed = $driver->parseWebhookPayload($request);
        $this->assertNotNull($parsed);
        $this->assertSame('ref-1', $parsed['reference']);
        $this->assertSame(1000, $parsed['amount_minor']);
        $this->assertTrue($parsed['success']);
    }
}
