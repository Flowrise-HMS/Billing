<?php

namespace Modules\Billing\Tests\Unit;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Mockery;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Billing\Gateways\Drivers\PaystackDriver;
use Modules\Billing\Gateways\Paystack\PaystackClientFactory;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentIntent;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class PaystackDriverTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Billing']);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_it_initializes_checkout_via_paystack_sdk(): void
    {
        $factory = $this->stubFactory([
            'authorization_url' => 'https://checkout.paystack.com/abc',
            'access_code' => 'access-123',
        ]);

        $intent = $this->createPaymentIntent('ref-abc');

        $config = BranchPaymentGatewayConfig::query()->create([
            'branch_id' => $intent->branch_id,
            'driver' => 'paystack',
            'display_name' => 'Paystack',
            'secret_key' => 'sk_test_secret',
            'is_enabled' => true,
            'test_mode' => true,
        ]);

        $driver = new PaystackDriver($factory);
        $result = $driver->createCheckout($config, $intent->fresh());

        $this->assertSame('https://checkout.paystack.com/abc', $result->checkout_url);
        $this->assertSame('access-123', $result->provider_reference);
    }

    public function test_it_marks_intent_failed_when_sdk_returns_no_authorization_url(): void
    {
        $factory = $this->stubFactory(['message' => 'Invalid key']);

        $intent = $this->createPaymentIntent('ref-fail');

        $config = BranchPaymentGatewayConfig::query()->create([
            'branch_id' => $intent->branch_id,
            'driver' => 'paystack',
            'display_name' => 'Paystack',
            'secret_key' => 'sk_bad',
            'is_enabled' => true,
            'test_mode' => true,
        ]);

        $driver = new PaystackDriver($factory);
        $result = $driver->createCheckout($config, $intent->fresh());

        $this->assertSame(PaymentIntentStatus::Failed, $result->status);
    }

    public function test_it_verifies_paystack_webhook_signature(): void
    {
        $secret = 'whsec_test';
        $body = [
            'event' => 'charge.success',
            'data' => ['reference' => 'ref-1', 'amount' => 5000, 'currency' => 'GHS', 'status' => 'success'],
        ];
        $json = json_encode($body);
        $signature = hash_hmac('sha512', $json, $secret);

        $request = Request::create(
            '/hook',
            'POST',
            $body,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature],
            $json
        );

        $config = new BranchPaymentGatewayConfig([
            'webhook_secret' => $secret,
        ]);

        $driver = new PaystackDriver(new PaystackClientFactory);

        $this->assertTrue($driver->verifyWebhookSignature($request, $config));

        $parsed = $driver->parseWebhookPayload($request);
        $this->assertNotNull($parsed);
        $this->assertSame('ref-1', $parsed['reference']);
        $this->assertTrue($parsed['success']);
    }

    /**
     * @param  array<string, mixed>  $initializeResponse
     */
    protected function stubFactory(array $initializeResponse): PaystackClientFactory
    {
        $transaction = new class($initializeResponse)
        {
            public function __construct(private array $response) {}

            public function initialize(array $params = []): array
            {
                return $this->response;
            }
        };

        $client = new class($transaction)
        {
            public function __construct(private object $transaction) {}

            public function transaction(): object
            {
                return $this->transaction;
            }
        };

        $factory = Mockery::mock(PaystackClientFactory::class);
        $factory->shouldReceive('make')->andReturn($client);

        return $factory;
    }

    protected function createPaymentIntent(string $reference): PaymentIntent
    {
        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(
            fn () => PatientFactory::new()->create(['branch_id' => $branch->id])
        );

        $invoice = Invoice::withoutEvents(function () use ($branch, $patient) {
            return Invoice::query()->withoutGlobalScopes()->create([
                'organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
                'status' => InvoiceStatus::Issued,
                'invoice_type' => InvoiceType::Standalone,
                'currency' => 'GHS',
                'total' => 50,
                'amount_paid' => 0,
            ]);
        });

        return PaymentIntent::query()->create([
            'invoice_id' => $invoice->id,
            'branch_id' => $branch->id,
            'gateway' => 'paystack',
            'status' => PaymentIntentStatus::Pending,
            'amount' => '50.00',
            'currency' => 'GHS',
            'client_reference' => $reference,
            'metadata' => ['customer_email' => 'patient@example.com'],
            'expires_at' => now()->addHours(2),
        ]);
    }
}
