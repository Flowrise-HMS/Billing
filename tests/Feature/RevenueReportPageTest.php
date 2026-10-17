<?php

declare(strict_types=1);

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Modules\Billing\Filament\Clusters\Billing\Pages\RevenueReport;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RevenueReportPageTest extends TestCase
{
    use DatabaseTransactions;

    public function test_revenue_report_page_renders_widgets_without_cramped_grid_column_wrapper(): void
    {
        Role::firstOrCreate(['name' => 'super_admin', 'guard_name' => 'web']);

        $user = User::factory()->create();
        $user->assignRole('super_admin');

        $component = Livewire::actingAs($user)->test(RevenueReport::class);

        $component->assertSuccessful();
        $component->assertDontSee('grid gap-4 md:grid-cols-2 mt-6');
        $component->assertSee('fi-wi');
    }
}
