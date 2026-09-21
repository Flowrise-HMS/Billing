<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentIntents\Pages\ListPaymentIntents;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentIntents\Pages\ViewPaymentIntent;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentIntents\PaymentIntentResource;
use Modules\Billing\Models\PaymentIntent;
use Modules\Core\Models\Branch;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Billing');
    $this->migrateModules(['Core', 'Patient', 'Billing']);
    $this->branch = Branch::factory()->create();
    $this->setCurrentBranch($this->branch);
});

FilamentResourceTestSuite::register([
    'resource' => PaymentIntentResource::class,
    'subject' => 'PaymentIntent',
    'model' => PaymentIntent::class,
    'listPage' => ListPaymentIntents::class,
    'viewPage' => ViewPaymentIntent::class,
    'hasBulkDelete' => false,
    'hasRecordDelete' => false,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): PaymentIntent => PaymentIntent::factory()->create([
        'branch_id' => $test->branch->id,
        ...$attributes,
    ]),
    'makeRecords' => fn (TestCase $test, int $count) => PaymentIntent::factory()->count($count)->create([
        'branch_id' => $test->branch->id,
    ]),
]);
