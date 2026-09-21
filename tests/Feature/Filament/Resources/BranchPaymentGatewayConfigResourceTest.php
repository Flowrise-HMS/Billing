<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Filament\Clusters\Billing\Resources\BranchPaymentGatewayConfigs\BranchPaymentGatewayConfigResource;
use Modules\Billing\Filament\Clusters\Billing\Resources\BranchPaymentGatewayConfigs\Pages\CreateBranchPaymentGatewayConfig;
use Modules\Billing\Filament\Clusters\Billing\Resources\BranchPaymentGatewayConfigs\Pages\EditBranchPaymentGatewayConfig;
use Modules\Billing\Filament\Clusters\Billing\Resources\BranchPaymentGatewayConfigs\Pages\ListBranchPaymentGatewayConfigs;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
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
    'resource' => BranchPaymentGatewayConfigResource::class,
    'subject' => 'BranchPaymentGatewayConfig',
    'model' => BranchPaymentGatewayConfig::class,
    'listPage' => ListBranchPaymentGatewayConfigs::class,
    'createPage' => CreateBranchPaymentGatewayConfig::class,
    'editPage' => EditBranchPaymentGatewayConfig::class,
    'hasBulkDelete' => false,
    'hasRecordDelete' => false,
    'hasTableDelete' => false,
    'userAttributes' => fn (TestCase $test): array => ['branch_id' => $test->branch->id],
    'makeRecord' => fn (TestCase $test, array $attributes = []): BranchPaymentGatewayConfig => BranchPaymentGatewayConfig::factory()->create([
        'branch_id' => $test->branch->id,
        'driver' => 'stripe',
        ...$attributes,
    ]),
    'makeRecords' => function (TestCase $test, int $count) {
        $drivers = ['stripe', 'paystack', 'hubtel'];
        $records = collect();

        for ($index = 0; $index < min($count, count($drivers)); $index++) {
            $records->push(BranchPaymentGatewayConfig::factory()->create([
                'branch_id' => $test->branch->id,
                'driver' => $drivers[$index],
                'display_name' => 'Gateway '.$drivers[$index],
            ]));
        }

        return $records;
    },
    'createForm' => fn (TestCase $test): array => [
        'branch_id' => $test->branch->id,
        'driver' => 'hubtel',
        'display_name' => 'Test Hubtel Gateway',
        'public_key' => 'pk_test_hubtel',
        'secret_key' => 'sk_test_hubtel',
        'webhook_secret' => 'whsec_hubtel',
        'is_enabled' => true,
        'test_mode' => true,
    ],
    'updateForm' => fn (TestCase $test, BranchPaymentGatewayConfig $record): array => [
        'branch_id' => $record->branch_id,
        'display_name' => 'Updated gateway name',
    ],
    'schemaState' => fn (mixed $test, BranchPaymentGatewayConfig $record): array => [
        'branch_id' => $record->branch_id,
        'driver' => $record->driver,
    ],
    'databaseHasOnCreate' => fn (mixed $test, array $payload): array => [
        'branch_id' => $payload['branch_id'],
        'driver' => $payload['driver'],
        'display_name' => $payload['display_name'],
    ],
]);
