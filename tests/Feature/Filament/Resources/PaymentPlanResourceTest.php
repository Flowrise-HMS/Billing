<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentPlanStatus;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Pages\CreatePaymentPlan;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Pages\ListPaymentPlans;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Pages\ViewPaymentPlan;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\PaymentPlanResource;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentPlan;
use Modules\Core\Models\Branch;
use Modules\Patient\Models\Patient;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Billing');
    $this->migrateModules(['Core', 'Patient', 'Billing']);
    $this->branch = Branch::factory()->create();
    $this->setCurrentBranch($this->branch);
    $this->patient = Patient::factory()->create(['branch_id' => $this->branch->id]);
    $this->invoice = Invoice::factory()->create([
        'branch_id' => $this->branch->id,
        'patient_id' => $this->patient->id,
        'status' => InvoiceStatus::Issued,
        'total' => '200.00',
        'amount_paid' => '0.00',
    ]);
});

FilamentResourceTestSuite::register([
    'resource' => PaymentPlanResource::class,
    'subject' => 'PaymentPlan',
    'model' => PaymentPlan::class,
    'listPage' => ListPaymentPlans::class,
    'createPage' => CreatePaymentPlan::class,
    'viewPage' => ViewPaymentPlan::class,
    'sortColumn' => null,
    'filter' => [
        'name' => 'status',
        'value' => PaymentPlanStatus::Active->value,
        'attribute' => 'status',
    ],
    'hasBulkDelete' => false,
    'hasRecordDelete' => false,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): PaymentPlan => PaymentPlan::factory()->create([
        'invoice_id' => Invoice::factory()->create([
            'branch_id' => $test->branch->id,
            'patient_id' => $test->patient->id,
            'status' => InvoiceStatus::Issued,
        ])->id,
        'status' => PaymentPlanStatus::Active,
        ...$attributes,
    ]),
    'makeRecords' => function (TestCase $test, int $count) {
        $records = collect();

        for ($index = 0; $index < $count; $index++) {
            $invoice = Invoice::factory()->create([
                'branch_id' => $test->branch->id,
                'patient_id' => $test->patient->id,
                'status' => InvoiceStatus::Issued,
            ]);
            $records->push(PaymentPlan::factory()->create([
                'invoice_id' => $invoice->id,
                'status' => PaymentPlanStatus::Active,
            ]));
        }

        return $records;
    },
    'createForm' => fn (TestCase $test): array => [
        'invoice_id' => $test->invoice->id,
        'down_payment' => '0.00',
        'installment_count' => 4,
        'frequency_days' => 14,
        'notes' => 'Factory payment plan',
    ],
    'createNotification' => 'Payment plan created with 4 installments.',
    'skipCreateRedirect' => false,
    'requiredValidation' => [
        'invoice is required' => [['invoice_id' => null], ['invoice_id' => 'required']],
        'installment count is required' => [['installment_count' => null], ['installment_count' => 'required']],
        'frequency is required' => [['frequency_days' => null], ['frequency_days' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'invoice_id' => $payload['invoice_id'],
        'installment_count' => $payload['installment_count'],
    ],
]);
