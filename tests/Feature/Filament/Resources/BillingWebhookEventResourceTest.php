<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Filament\Clusters\Billing\Resources\BillingWebhookEvents\BillingWebhookEventResource;
use Modules\Billing\Filament\Clusters\Billing\Resources\BillingWebhookEvents\Pages\ListBillingWebhookEvents;
use Modules\Billing\Filament\Clusters\Billing\Resources\BillingWebhookEvents\Pages\ViewBillingWebhookEvent;
use Modules\Billing\Models\BillingWebhookEvent;
use Tests\Support\FilamentResourceTestSuite;
use Tests\TestCase;

uses(TestCase::class, DatabaseTransactions::class);

beforeEach(function (): void {
    $this->requireModule('Billing');
    $this->migrateModules(['Core', 'Patient', 'Billing']);
});

FilamentResourceTestSuite::register([
    'resource' => BillingWebhookEventResource::class,
    'subject' => 'BillingWebhookEvent',
    'model' => BillingWebhookEvent::class,
    'listPage' => ListBillingWebhookEvents::class,
    'viewPage' => ViewBillingWebhookEvent::class,
    'searchColumn' => 'idempotency_key',
    'hasBulkDelete' => false,
    'hasRecordDelete' => false,
    'makeRecords' => function (TestCase $test, int $count) {
        $records = collect();

        for ($index = 0; $index < $count; $index++) {
            $records->push(BillingWebhookEvent::factory()->create([
                'idempotency_key' => 'wh-'.$index.'-'.fake()->unique()->uuid(),
            ]));
        }

        return $records;
    },
]);
