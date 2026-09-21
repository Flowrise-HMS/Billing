<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\Pages\ListPayments;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\Pages\ViewPayment;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\PaymentResource;
use Modules\Billing\Models\Payment;
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
    'resource' => PaymentResource::class,
    'subject' => 'Payment',
    'model' => Payment::class,
    'listPage' => ListPayments::class,
    'viewPage' => ViewPayment::class,
    'hasBulkDelete' => false,
    'hasRecordDelete' => false,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): Payment => Payment::factory()->create([
        'branch_id' => $test->branch->id,
        'patient_id' => $test->patient->id,
        ...$attributes,
    ]),
    'makeRecords' => fn (TestCase $test, int $count) => Payment::factory()->count($count)->create([
        'branch_id' => $test->branch->id,
        'patient_id' => $test->patient->id,
    ]),
]);
