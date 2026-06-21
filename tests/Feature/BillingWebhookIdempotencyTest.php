<?php

namespace Modules\Billing\Tests\Feature;

use Illuminate\Http\Request;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Enums\PaymentIntentStatus;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PaymentIntent;
use Modules\Billing\Services\WebhookPaymentService;
use Modules\Core\Database\Factories\BranchFactory;
use Modules\Patient\Database\Factories\PatientFactory;
use Modules\Patient\Models\Patient;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Tests\TestCase;

class BillingWebhookIdempotencyTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Patient', 'Clinical', 'Appointment', 'Billing']);
    }

    public function test_duplicate_paystack_webhook_does_not_double_record_payment(): void
    {
        $branch = BranchFactory::new()->create();
        $patient = Patient::withoutEvents(fn () => PatientFactory::new()->create(['branch_id' => $branch->id]));

        $invoice = Invoice::withoutEvents(function () use ($branch, $patient) {
            $inv = Invoice::query()->withoutGlobalScopes()->create([
                'organization_id' => $branch->organization_id,
                'branch_id' => $branch->id,
                'patient_id' => $patient->id,
                'invoice_number' => Invoice::generateInvoiceNumber((string) $branch->id),
                'status' => InvoiceStatus::Issued,
                'invoice_type' => InvoiceType::Standalone,
                'currency' => 'GHS',
                'issued_at' => now(),
                'subtotal' => 50,
                'discount_total' => 0,
                'tax_total' => 0,
                'total' => 50,
                'amount_paid' => 0,
            ]);

            InvoiceLine::query()->create([
                'invoice_id' => $inv->id,
                'billable_type' => null,
                'billable_id' => null,
                'description' => 'Test',
                'quantity' => 1,
                'unit_price' => 50,
                'discount_amount' => 0,
                'tax_amount' => 0,
                'line_total' => 50,
                'amount_paid' => 0,
                'line_status' => InvoiceLineStatus::Unpaid,
                'patient_responsibility_amount' => 50,
            ]);

            return $inv->fresh(['lines']);
        });

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

        $reference = 'pay-ref-'.uniqid();
        PaymentIntent::query()->create([
            'invoice_id' => $invoice->id,
            'branch_id' => $branch->id,
            'gateway' => 'paystack',
            'status' => PaymentIntentStatus::Pending,
            'amount' => 50,
            'currency' => 'GHS',
            'client_reference' => $reference,
        ]);

        $body = [
            'event' => 'charge.success',
            'data' => [
                'reference' => $reference,
                'amount' => 5000,
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

        $service = app(WebhookPaymentService::class);
        $service->process($request, 'paystack', (string) $branch->id);
        $service->process($request, 'paystack', (string) $branch->id);

        $this->assertSame(1, Payment::query()->where('branch_id', $branch->id)->count());
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
    }
}
