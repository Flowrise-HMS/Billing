<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Str;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Filament\Clusters\Billing\Resources\RefundsRegister\Pages\ListRefunds;
use Modules\Billing\Filament\Clusters\Billing\Resources\RefundsRegister\RefundsRegisterResource;
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
    'resource' => RefundsRegisterResource::class,
    'subject' => 'Payment',
    'model' => Payment::class,
    'listPage' => ListRefunds::class,
    'searchColumn' => 'metadata.reason',
    'sortColumn' => 'received_at',
    'hasBulkDelete' => false,
    'hasRecordDelete' => false,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): Payment => Payment::query()->create([
        'patient_id' => $test->patient->id,
        'branch_id' => $test->branch->id,
        'method' => PaymentMethod::Gateway,
        'gateway' => 'refund',
        'type' => PaymentType::Refund,
        'amount' => '-25.00',
        'currency' => 'GHS',
        'provider_transaction_id' => 'txn-'.Str::uuid(),
        'received_at' => now(),
        'metadata' => ['reason' => 'OverchargeZephyr'],
        ...$attributes,
    ]),
    'makeRecords' => function (TestCase $test, int $count) {
        $records = collect();

        for ($index = 0; $index < $count; $index++) {
            $records->push(Payment::query()->create([
                'patient_id' => $test->patient->id,
                'branch_id' => $test->branch->id,
                'method' => PaymentMethod::Gateway,
                'gateway' => 'refund',
                'type' => PaymentType::Refund,
                'amount' => '-'.($index + 10).'.00',
                'currency' => 'GHS',
                'provider_transaction_id' => 'txn-'.Str::uuid(),
                'received_at' => now()->subMinutes($index),
                'metadata' => ['reason' => 'RefundReason'.$index],
            ]));
        }

        return $records;
    },
]);
