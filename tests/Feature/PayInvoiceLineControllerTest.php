<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\URL;
use Mockery;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Billing\Gateways\Paystack\PaystackClientFactory;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\PaymentIntent;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Tests\TestCase;

class PayInvoiceLineControllerTest extends TestCase
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

    public function test_happy_path_redirects_to_gateway_checkout_and_issues_draft_invoice(): void
    {
        [$branch, $invoice, $line] = $this->seedDraftInvoiceLine();
        $this->enablePaystack($branch->id);
        $this->stubPaystack();

        $url = URL::temporarySignedRoute('billing.public.pay-line', now()->addDay(), ['line' => $line->id]);

        $response = $this->get($url);

        $response->assertRedirect('https://checkout.paystack.com/xyz');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Issued, $invoice->status);

        // Issuing the Draft invoice (required before a payment can be recorded
        // against it, see CheckoutSessionService::createForInvoice) also fires
        // the pre-existing InvoiceIssued -> InvoiceIssuedNotification pipeline,
        // which independently mints its own whole-invoice PaymentIntent via
        // ResolvesInvoiceCheckoutUrl. So more than one PaymentIntent can exist
        // after this click — assert the one this flow cares about exists and
        // is scoped to exactly this line, not a total count.
        $lineScoped = PaymentIntent::query()
            ->where('invoice_id', $invoice->id)
            ->get()
            ->first(fn (PaymentIntent $intent) => $intent->line_ids === [$line->id]);

        $this->assertNotNull($lineScoped);
        $this->assertSame('https://checkout.paystack.com/xyz', $lineScoped->checkout_url);
    }

    public function test_second_click_reuses_the_same_pending_intent(): void
    {
        [$branch, , $line] = $this->seedDraftInvoiceLine();
        $this->enablePaystack($branch->id);
        $this->stubPaystack();

        $url = URL::temporarySignedRoute('billing.public.pay-line', now()->addDay(), ['line' => $line->id]);

        $this->get($url)->assertRedirect('https://checkout.paystack.com/xyz');
        $this->get($url)->assertRedirect('https://checkout.paystack.com/xyz');

        $this->assertSame(1, PaymentIntent::query()->where('line_ids', json_encode([$line->id]))->count());
    }

    public function test_already_paid_line_shows_confirmation_without_calling_gateway(): void
    {
        [, , $line] = $this->seedDraftInvoiceLine();
        $line->update(['line_status' => InvoiceLineStatus::Paid, 'amount_paid' => $line->line_total]);

        $url = URL::temporarySignedRoute('billing.public.pay-line', now()->addDay(), ['line' => $line->id]);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('Already paid');
        $this->assertSame(0, PaymentIntent::query()->count());
    }

    public function test_void_line_shows_cancelled_page(): void
    {
        [, , $line] = $this->seedDraftInvoiceLine();
        $line->update(['line_status' => InvoiceLineStatus::Void]);

        $url = URL::temporarySignedRoute('billing.public.pay-line', now()->addDay(), ['line' => $line->id]);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('cancelled');
    }

    public function test_no_gateway_configured_shows_friendly_page(): void
    {
        [, , $line] = $this->seedDraftInvoiceLine();

        $url = URL::temporarySignedRoute('billing.public.pay-line', now()->addDay(), ['line' => $line->id]);

        $response = $this->get($url);

        $response->assertOk();
        $response->assertSee('not available yet');
    }

    public function test_tampered_signature_is_rejected(): void
    {
        [, , $line] = $this->seedDraftInvoiceLine();

        $url = URL::temporarySignedRoute('billing.public.pay-line', now()->addDay(), ['line' => $line->id]);
        $tampered = $url.'&tampered=1';

        $this->get($tampered)->assertForbidden();
    }

    public function test_expired_signature_is_rejected(): void
    {
        [, , $line] = $this->seedDraftInvoiceLine();

        $url = URL::temporarySignedRoute('billing.public.pay-line', now()->subMinute(), ['line' => $line->id]);

        $this->get($url)->assertForbidden();
    }

    public function test_stale_amount_intent_is_replaced_not_reused(): void
    {
        [$branch, $invoice, $line] = $this->seedDraftInvoiceLine();
        $this->enablePaystack($branch->id);
        $this->stubPaystack();

        $stale = PaymentIntent::query()->create([
            'invoice_id' => $invoice->id,
            'branch_id' => $branch->id,
            'gateway' => 'paystack',
            'status' => PaymentIntentStatus::Pending,
            'amount' => '999.00',
            'currency' => 'GHS',
            'line_ids' => [$line->id],
            'client_reference' => 'stale-ref',
            'expires_at' => now()->addHours(2),
        ]);

        $url = URL::temporarySignedRoute('billing.public.pay-line', now()->addDay(), ['line' => $line->id]);
        $this->get($url)->assertRedirect('https://checkout.paystack.com/xyz');

        $stale->refresh();
        $this->assertSame(PaymentIntentStatus::Expired, $stale->status);

        // See the happy-path test for why more than one intent can legitimately
        // exist here (issuing the Draft invoice fires InvoiceIssuedNotification,
        // which mints its own whole-invoice intent). What matters is that a
        // *fresh*, correctly-amounted, Pending intent for this line exists,
        // distinct from the stale one.
        $fresh = PaymentIntent::query()
            ->where('invoice_id', $invoice->id)
            ->where('status', PaymentIntentStatus::Pending)
            ->where('id', '!=', $stale->id)
            ->get()
            ->first(fn (PaymentIntent $intent) => $intent->line_ids === [$line->id]);

        $this->assertNotNull($fresh);
        $this->assertSame('45.00', (string) $fresh->amount);
    }

    public function test_rate_limit_returns_429_after_the_configured_number_of_requests(): void
    {
        [$branch, , $line] = $this->seedDraftInvoiceLine();
        $this->enablePaystack($branch->id);
        $this->stubPaystack();

        $limit = (int) config('billing.public_pay_link.rate_limit_per_minute', 10);
        $url = URL::temporarySignedRoute('billing.public.pay-line', now()->addDay(), ['line' => $line->id]);

        for ($i = 0; $i < $limit; $i++) {
            $this->get($url)->assertRedirect('https://checkout.paystack.com/xyz');
        }

        $this->get($url)->assertStatus(429);
    }

    /**
     * @return array{0: \Modules\Core\Models\Branch, 1: Invoice, 2: InvoiceLine}
     */
    protected function seedDraftInvoiceLine(): array
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

        return [$branch, $invoice, $line];
    }

    protected function enablePaystack(string $branchId): void
    {
        BranchPaymentGatewayConfig::query()->create([
            'branch_id' => $branchId,
            'driver' => 'paystack',
            'display_name' => 'Paystack',
            'secret_key' => 'sk_test',
            'is_enabled' => true,
            'test_mode' => true,
        ]);
    }

    protected function stubPaystack(): void
    {
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
        $factory = Mockery::mock(PaystackClientFactory::class);
        $factory->shouldReceive('make')->andReturn($client);
        $this->app->instance(PaystackClientFactory::class, $factory);
    }
}
