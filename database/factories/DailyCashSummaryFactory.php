<?php

namespace Modules\Billing\Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Models\DailyCashSummary;
use Modules\Core\Database\Factories\BranchFactory;

class DailyCashSummaryFactory extends Factory
{
    protected $model = DailyCashSummary::class;

    public function definition(): array
    {
        return [
            'branch_id' => BranchFactory::new()->create()->id,
            'cashier_id' => User::factory()->create()->id,
            'summary_date' => now()->toDateString(),
            'opening_cash' => '0.00',
            'change_given' => '0.00',
            'counted_closing' => null,
            'expected_closing' => '0.00',
            'variance' => '0.00',
            'status' => 'open',
        ];
    }
}
