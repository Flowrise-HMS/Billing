<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Enums\DailyCashSummaryStatus;
use Modules\Billing\Models\DailyCashSummary;
use Modules\Core\Database\Factories\BranchFactory;
use Tests\TestCase;

class DailyCashSummaryModelTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Billing']);
    }

    public function test_summary_has_expected_defaults_and_relations(): void
    {
        $branch = BranchFactory::new()->create();
        $cashier = User::factory()->create();
        $finalizer = User::factory()->create();

        $summary = DailyCashSummary::create([
            'branch_id' => $branch->id,
            'cashier_id' => $cashier->id,
            'summary_date' => now()->toDateString(),
            'opening_cash' => '100.00',
        ]);

        $this->assertSame(DailyCashSummaryStatus::Open, $summary->status);
        $this->assertSame('0.00', $summary->expected_closing);
        $this->assertSame('100.00', $summary->opening_cash);
        $this->assertTrue($summary->branch->is($branch));
        $this->assertTrue($summary->cashier->is($cashier));
        $this->assertNull($summary->finalizedByUser);
    }

    public function test_unique_branch_cashier_date_is_enforced(): void
    {
        $branch = BranchFactory::new()->create();
        $cashier = User::factory()->create();

        DailyCashSummary::create([
            'branch_id' => $branch->id,
            'cashier_id' => $cashier->id,
            'summary_date' => '2026-08-15',
        ]);

        $this->expectException(QueryException::class);

        DailyCashSummary::create([
            'branch_id' => $branch->id,
            'cashier_id' => $cashier->id,
            'summary_date' => '2026-08-15',
        ]);
    }
}
