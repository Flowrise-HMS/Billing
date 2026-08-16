<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Billing\Filament\Clusters\Billing\Pages\RefundsAndWriteOffs;
use Tests\TestCase;

class RefundsAndWriteOffsPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Billing']);
    }

    public function test_page_renders(): void
    {
        $user = User::factory()->create();
        Gate::before(fn () => true);

        Livewire::actingAs($user)
            ->test(RefundsAndWriteOffs::class)
            ->assertOk();
    }
}
