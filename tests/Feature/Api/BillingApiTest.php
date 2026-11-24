<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PatientDeposit;
use Modules\Billing\Models\Payment;
use Modules\Billing\Models\PaymentPlan;
use Modules\Core\Models\Branch;
use Modules\Core\Models\Service;
use Modules\Patient\Models\Patient;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Support\ApiResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

$modules = ['Core', 'Patient', 'Billing'];

function billingApiPatient(Branch $branch): Patient
{
    return Patient::withoutEvents(fn () => Patient::factory()->create(['branch_id' => $branch->id]));
}

function billingApiInvoice(Branch $branch, array $attributes = []): Invoice
{
    return Invoice::factory()->create([
        'branch_id' => $branch->id,
        'organization_id' => $branch->organization_id,
        'patient_id' => billingApiPatient($branch)->id,
        ...$attributes,
    ]);
}

describe('invoices', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'invoices',
        'subject' => 'Invoice',
        'scope' => 'billing',
        'modules' => $modules,
        'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => billingApiInvoice($branch, $attributes),
        'store' => fn (TestCase $test, Branch $branch): array => ['patient_id' => billingApiPatient($branch)->id],
        'invalid' => [['currency' => 'GHANA'], 'currency'],
        'update' => fn (TestCase $test, $record): array => ['due_at' => now()->addDays(10)->toDateString()],
        'destroy' => true,
    ]);
});

describe('payments', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'payments',
        'subject' => 'Payment',
        'scope' => 'billing',
        'modules' => $modules,
        'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => Payment::factory()->create(['branch_id' => $branch->id, 'patient_id' => billingApiPatient($branch)->id, ...$attributes]),
    ]);
});

describe('deposits', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'deposits',
        'subject' => 'PatientDeposit',
        'scope' => 'billing',
        'modules' => $modules,
        'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => PatientDeposit::factory()->create(['branch_id' => $branch->id, 'patient_id' => billingApiPatient($branch)->id, ...$attributes]),
        'store' => fn (TestCase $test, Branch $branch): array => ['patient_id' => billingApiPatient($branch)->id, 'amount' => 100, 'method' => 'cash'],
        'invalid' => [['amount' => -5, 'method' => 'cash'], 'amount'],
    ]);
});

describe('payment plans', function () use ($modules): void {
    ApiResourceTestSuite::register([
        'uri' => 'payment-plans',
        'subject' => 'PaymentPlan',
        'scope' => 'billing',
        'modules' => $modules,
        'make' => fn (TestCase $test, Branch $branch, array $attributes = []) => PaymentPlan::factory()->create(['invoice_id' => billingApiInvoice($branch)->id, ...$attributes]),
    ]);
});

describe('billing workflow', function () use ($modules): void {
    beforeEach(function () use ($modules): void {
        $this->requireModule('Api');
        $this->migrateModules($modules);
        $this->branch = Branch::factory()->create();
        $this->user = User::factory()->create(['branch_id' => $this->branch->id]);

        foreach (['Invoice', 'Payment', 'PatientDeposit', 'PaymentPlan'] as $subject) {
            foreach (['ViewAny', 'View', 'Create', 'Update'] as $action) {
                Permission::findOrCreate("{$action} {$subject}", 'web');
                $this->user->givePermissionTo("{$action} {$subject}");
            }
        }
        Permission::findOrCreate('Delete Invoice', 'web');
        $this->user->givePermissionTo('Delete Invoice');
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $this->withToken($this->user->createToken('t', ['billing:write'])->plainTextToken);
    });

    it('builds, issues, pays and refunds an invoice', function (): void {
        $patient = billingApiPatient($this->branch);
        $service = Service::factory()->create();

        $id = $this->postJson('/api/v1/invoices', ['patient_id' => $patient->id])->assertCreated()->assertJsonPath('data.status', InvoiceStatus::Draft->value)->json('data.id');

        $this->postJson("/api/v1/invoices/{$id}/lines", ['service_id' => $service->id, 'quantity' => 2, 'unit_price' => 50])
            ->assertOk()
            ->assertJsonPath('data.lines.0.quantity', 2)
            ->assertJsonPath('data.total', '100.00');

        $this->postJson("/api/v1/invoices/{$id}/issue")->assertOk()->assertJsonPath('data.status', InvoiceStatus::Issued->value);
        $this->postJson("/api/v1/invoices/{$id}/lines", ['description' => 'Late item', 'unit_price' => 5])->assertUnprocessable();
        $this->deleteJson("/api/v1/invoices/{$id}")->assertUnprocessable();

        $this->postJson('/api/v1/payments', ['invoice_id' => $id, 'amount' => 150, 'method' => 'cash'])->assertUnprocessable();
        $paymentId = $this->postJson('/api/v1/payments', ['invoice_id' => $id, 'amount' => 60, 'method' => 'mobile_money'])
            ->assertCreated()
            ->assertJsonPath('data.amount', '60.00')
            ->json('data.id');

        $this->getJson("/api/v1/invoices/{$id}")->assertJsonPath('data.balance_due', '40.00');

        $this->postJson("/api/v1/payments/{$paymentId}/refund", ['amount' => 100])->assertUnprocessable();
        $this->postJson("/api/v1/payments/{$paymentId}/refund", ['amount' => 10, 'reason' => 'Overcharge'])
            ->assertOk()
            ->assertJsonPath('data.type', PaymentType::Refund->value);

        $this->getJson("/api/v1/invoices/{$id}")->assertJsonPath('data.balance_due', '50.00');
    });

    it('voids an issued unpaid invoice', function (): void {
        $invoice = billingApiInvoice($this->branch, ['status' => InvoiceStatus::Issued, 'issued_at' => now()]);

        $this->postJson("/api/v1/invoices/{$invoice->id}/void")->assertOk()->assertJsonPath('data.status', InvoiceStatus::Void->value);
    });

    it('records a deposit and applies it to an invoice', function (): void {
        $patient = billingApiPatient($this->branch);
        $service = Service::factory()->create();
        $invoiceId = $this->postJson('/api/v1/invoices', ['patient_id' => $patient->id])->json('data.id');
        $this->postJson("/api/v1/invoices/{$invoiceId}/lines", ['service_id' => $service->id, 'unit_price' => 80]);
        $this->postJson("/api/v1/invoices/{$invoiceId}/issue")->assertOk();

        $depositId = $this->postJson('/api/v1/deposits', ['patient_id' => $patient->id, 'amount' => 100, 'method' => 'cash'])
            ->assertCreated()
            ->assertJsonPath('data.unallocated_balance', '100.00')
            ->json('data.id');

        $this->postJson("/api/v1/deposits/{$depositId}/apply", ['invoice_id' => $invoiceId, 'amount' => 80])
            ->assertOk()
            ->assertJsonPath('data.unallocated_balance', '20.00');
        $this->getJson("/api/v1/invoices/{$invoiceId}")->assertJsonPath('data.balance_due', '0.00');
    });

    it('creates and cancels a payment plan', function (): void {
        $patient = billingApiPatient($this->branch);
        $service = Service::factory()->create();
        $invoiceId = $this->postJson('/api/v1/invoices', ['patient_id' => $patient->id])->json('data.id');
        $this->postJson("/api/v1/invoices/{$invoiceId}/lines", ['service_id' => $service->id, 'unit_price' => 300]);

        $this->postJson('/api/v1/payment-plans', ['invoice_id' => $invoiceId, 'installment_count' => 3])->assertUnprocessable();
        $this->postJson("/api/v1/invoices/{$invoiceId}/issue")->assertOk();

        $planId = $this->postJson('/api/v1/payment-plans', ['invoice_id' => $invoiceId, 'installment_count' => 3, 'frequency_days' => 30])
            ->assertCreated()
            ->assertJsonCount(3, 'data.installments')
            ->json('data.id');

        $this->postJson("/api/v1/payment-plans/{$planId}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
    });
});
