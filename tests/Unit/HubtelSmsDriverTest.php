<?php

namespace Modules\Billing\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Http;
use Modules\Billing\Services\Sms\Drivers\HubtelSmsDriver;
use Tests\TestCase;

class HubtelSmsDriverTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core']);

        config([
            'billing.notifications.sms.hubtel.client_id' => 'client-id',
            'billing.notifications.sms.hubtel.client_secret' => 'client-secret',
            'billing.notifications.sms.hubtel.sender_id' => 'FlowRise',
            'billing.notifications.sms.hubtel.endpoint' => 'https://sms.hubtel.com/v1/messages/send',
        ]);
    }

    public function test_successful_send_posts_expected_payload_with_basic_auth(): void
    {
        Http::fake(['sms.hubtel.com/*' => Http::response(['status' => 0], 200)]);

        $result = app(HubtelSmsDriver::class)->send('+233500000001', 'Pay lab GHS 45: https://example.test/pay/lines/abc');

        $this->assertTrue($result);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://sms.hubtel.com/v1/messages/send'
                && $request['To'] === '+233500000001'
                && $request['From'] === 'FlowRise'
                && str_contains($request['Content'], 'Pay lab')
                && $request->hasHeader('Authorization');
        });
    }

    public function test_failed_response_returns_false_without_throwing(): void
    {
        Http::fake(['sms.hubtel.com/*' => Http::response(['status' => 1, 'message' => 'bad request'], 400)]);

        $result = app(HubtelSmsDriver::class)->send('+233500000001', 'A message');

        $this->assertFalse($result);
    }

    public function test_network_exception_returns_false_without_throwing(): void
    {
        Http::fake(function () {
            throw new \Illuminate\Http\Client\ConnectionException('Connection failed');
        });

        $result = app(HubtelSmsDriver::class)->send('+233500000001', 'A message');

        $this->assertFalse($result);
    }
}
