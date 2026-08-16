<?php

namespace Modules\Billing\Tests\Feature;

use Filament\Schemas\Schema;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Filament\Actions\WriteOffLinesAction;
use Tests\TestCase;

class WriteOffReasonCaptureTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Billing']);
    }

    public function test_write_off_action_requires_a_reason(): void
    {
        $action = WriteOffLinesAction::make();
        $schema = $action->getSchema(Schema::make());

        $this->assertNotNull($schema);
        $names = collect($schema->getComponents())->map(fn ($component) => $component->getName())->all();
        $this->assertContains('reason', $names);
    }
}
