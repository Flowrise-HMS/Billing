<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Modules\Billing\Enums\DailyCashSummaryStatus;
use Modules\Billing\Filament\Clusters\Billing\Pages\DailyCashCloseout;
use Modules\Billing\Models\DailyCashSummary;
use Modules\Core\Database\Factories\BranchFactory;
use Tests\TestCase;

class DailyCashCloseoutPageTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Billing']);
    }

    private function actingAsUser(): User
    {
        $user = User::factory()->create();
        Gate::before(fn () => true);

        return $user;
    }

    public function test_page_renders(): void
    {
        $user = $this->actingAsUser();
        BranchFactory::new()->create();

        Livewire::actingAs($user)
            ->test(DailyCashCloseout::class)
            ->assertOk();
    }

    public function test_finalize_creates_locked_summary(): void
    {
        $user = $this->actingAsUser();
        $branch = BranchFactory::new()->create();

        Livewire::actingAs($user)
            ->test(DailyCashCloseout::class)
            ->set('summaryDate', now()->toDateString())
            ->set('branchId', $branch->id)
            ->set('openingCash', '200.00')
            ->set('countedClosing', '300.00')
            ->call('loadCloseout')
            ->call('finalizeCashier')
            ->assertOk();

        $summary = DailyCashSummary::where('cashier_id', $user->id)->first();
        $this->assertNotNull($summary);
        $this->assertSame(DailyCashSummaryStatus::Finalized, $summary->status);
        $this->assertSame($user->id, (int) $summary->finalized_by);
        $this->assertSame('200.00', $summary->opening_cash);
        $this->assertSame('300.00', $summary->counted_closing);
    }

    public function test_reopen_unlocks_summary(): void
    {
        $user = $this->actingAsUser();
        $branch = BranchFactory::new()->create();

        DailyCashSummary::create([
            'branch_id' => $branch->id,
            'cashier_id' => $user->id,
            'summary_date' => now()->toDateString(),
            'opening_cash' => '0.00',
            'counted_closing' => null,
            'expected_closing' => '0.00',
            'variance' => '0.00',
            'status' => DailyCashSummaryStatus::Finalized,
            'finalized_at' => now(),
            'finalized_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(DailyCashCloseout::class)
            ->set('summaryDate', now()->toDateString())
            ->set('branchId', $branch->id)
            ->call('loadCloseout')
            ->call('reopenCashier')
            ->assertOk();

        $summary = DailyCashSummary::where('cashier_id', $user->id)->firstOrFail();
        $this->assertSame(DailyCashSummaryStatus::Open, $summary->status);
        $this->assertNull($summary->finalized_at);
    }
}
