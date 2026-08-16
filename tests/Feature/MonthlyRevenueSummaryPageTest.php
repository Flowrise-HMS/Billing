<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Billing\Filament\Clusters\Billing\Pages\MonthlyRevenueSummary;
use Modules\Core\Database\Factories\BranchFactory;
use Tests\TestCase;

class MonthlyRevenueSummaryPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Billing']);
    }

    public function test_page_renders_and_runs_summary(): void
    {
        $user = User::factory()->create();
        Gate::before(fn () => true);
        $branch = BranchFactory::new()->create();

        Livewire::actingAs($user)
            ->test(MonthlyRevenueSummary::class)
            ->set('month', now()->format('Y-m'))
            ->set('branchId', $branch->id)
            ->call('loadSummary')
            ->assertOk()
            ->assertSet('summary.revenue_total', '0');
    }
}
