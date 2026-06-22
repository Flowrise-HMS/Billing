<?php

namespace Modules\Billing\Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Modules\Billing\Models\BranchPaymentGatewayConfig;
use Modules\Core\Models\Branch;

class BranchPaymentGatewayConfigFactory extends Factory
{
    protected $model = BranchPaymentGatewayConfig::class;

    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'driver' => fake()->randomElement(['stripe', 'paystack', 'hubtel']),
            'display_name' => fake()->company().' Gateway',
            'public_key' => 'pk_test_'.fake()->uuid(),
            'secret_key' => 'sk_test_'.fake()->uuid(),
            'webhook_secret' => 'whsec_'.fake()->uuid(),
            'is_enabled' => true,
            'test_mode' => true,
        ];
    }
}
