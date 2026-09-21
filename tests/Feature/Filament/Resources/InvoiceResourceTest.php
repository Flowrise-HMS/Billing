<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages\CreateInvoice;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages\EditInvoice;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages\ListInvoices;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages\ViewInvoice;
use Modules\Billing\Models\Invoice;
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
});

FilamentResourceTestSuite::register([
    'resource' => InvoiceResource::class,
    'subject' => 'Invoice',
    'model' => Invoice::class,
    'listPage' => ListInvoices::class,
    'createPage' => CreateInvoice::class,
    'editPage' => EditInvoice::class,
    'viewPage' => ViewInvoice::class,
    'searchColumn' => 'invoice_number',
    'sortColumn' => 'invoice_number',
    'filter' => [
        'name' => 'invoice_type',
        'value' => InvoiceType::Standalone->value,
        'attribute' => 'invoice_type',
    ],
    'hasBulkDelete' => false,
    'hasRecordDelete' => false,
    'hasTableDelete' => true,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): Invoice => Invoice::factory()->create([
        'branch_id' => $test->branch->id,
        'patient_id' => $test->patient->id,
        'invoice_type' => InvoiceType::Standalone,
        ...$attributes,
    ]),
    'makeRecords' => fn (TestCase $test, int $count) => Invoice::factory()->count($count)->create([
        'branch_id' => $test->branch->id,
        'patient_id' => $test->patient->id,
        'invoice_type' => InvoiceType::Standalone,
    ]),
    'createForm' => fn (TestCase $test): array => [
        'patient_id' => $test->patient->id,
        'invoice_type' => InvoiceType::Standalone->value,
        'currency' => 'GHS',
    ],
    'updateForm' => fn (): array => [
        'currency' => 'GHS',
        'invoice_type' => InvoiceType::Standalone->value,
    ],
    'schemaState' => fn (mixed $test, Invoice $record): array => [
        'patient_id' => $record->patient_id,
        'invoice_type' => $record->invoice_type?->value,
    ],
    'requiredValidation' => [
        'patient is required' => [['patient_id' => null], ['patient_id' => 'required']],
        'invoice type is required' => [['invoice_type' => null], ['invoice_type' => 'required']],
        'currency is required' => [['currency' => null], ['currency' => 'required']],
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'patient_id' => $payload['patient_id'],
        'invoice_type' => $payload['invoice_type'],
        'currency' => $payload['currency'],
    ],
]);
