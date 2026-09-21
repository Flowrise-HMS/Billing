<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Context;
use Mockery;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Billing\Gateways\Paystack\PaystackClientFactory;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\CheckoutSessionService;
use Modules\Billing\Services\WebhookPaymentService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class CheckoutSessionServiceDraftInvoiceTest extends TestCase
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

    public function test_checkout_session_for_draft_invoice_line_issues_it_first_and_webhook_records_payment(): void
    {
        $branch = BranchFactory::new()->create();
        Context::add('current_branch_id', $branch->id);

        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));

        $invoice = Invoice::withoutEvents(function () use ($branch, $patient) {
            return Invoice::query()->withoutGlobalScopes()->create([
                'organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
                'status' => InvoiceStatus::Draft,
                'invoice_type' => InvoiceType::Standalone,
                'currency' => 'GHS',
                'subtotal' => 45,
                'discount_total' => 0,
                'tax_total' => 0,
                'total' => 45,
                'amount_paid' => 0,
            ]);
        });

        $line = InvoiceLine::query()->create([
            'invoice_id' => $invoice->id,
            'billable_type' => null,
            'billable_id' => null,
            'description' => 'Full Blood Count',
            'quantity' => 1,
            'unit_price' => 45,
            'discount_amount' => 0,
            'tax_amount' => 0,
            'line_total' => 45,
            'amount_paid' => 0,
            'line_status' => InvoiceLineStatus::Unpaid,
            'patient_responsibility_amount' => 45,
        ]);

        $webhookSecret = 'test-webhook-secret';
        BranchPaymentGatewayConfig::query()->create([
            'branch_id' => $branch->id,
            'driver' => 'paystack',
            'display_name' => 'Paystack',
            'secret_key' => 'sk_test',
            'webhook_secret' => $webhookSecret,
            'is_enabled' => true,
            'test_mode' => true,
        ]);

        $factory = Mockery::mock(PaystackClientFactory::class);
        $transaction = new class
        {
            public function initialize(array $params = []): array
            {
                return [
                    'authorization_url' => 'https://checkout.paystack.com/xyz',
                    'access_code' => 'access-xyz',
                    'reference' => $params['reference'] ?? 'ref',
                ];
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
        $factory->shouldReceive('make')->andReturn($client);
        $this->app->instance(PaystackClientFactory::class, $factory);

        $intent = app(CheckoutSessionService::class)->createForInvoice(
            $invoice->fresh(),
            'paystack',
            [],
            [$line->id],
        );

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status, 'Draft invoice must be issued before a checkout session is minted, or the webhook cannot record the payment.');
        $this->assertNotNull($intent->checkout_url);

        $body = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $intent->client_reference,
                'amount' => 4500,
                'currency' => 'GHS',
                'status' => 'success',
            ],
        ];
        $json = json_encode($body);
        $signature = hash_hmac('sha512', $json, $webhookSecret);

        $request = Request::create(
            '/api/billing/webhooks/paystack/'.$branch->id,
            'POST',
            $body,
            [],
            [],
            ['CONTENT_TYPE' => 'application/json', 'HTTP_X_PAYSTACK_SIGNATURE' => $signature],
            $json
        );

        app(WebhookPaymentService::class)->process($request, 'paystack', (string) $branch->id);

        $line->refresh();
        $this->assertSame(InvoiceLineStatus::Paid, $line->line_status);
        $this->assertSame(1, Payment::query()->where('branch_id', $branch->id)->count());

        $intent->refresh();
        $this->assertSame(PaymentIntentStatus::Succeeded, $intent->status);
    }
}
