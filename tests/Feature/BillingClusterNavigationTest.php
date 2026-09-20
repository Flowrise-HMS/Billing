<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Filament\Facades\Filament;
use Filament\Navigation\NavigationGroup;
use Filament\Navigation\NavigationItem;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\RefundsRegister\RefundsRegisterResource;
use Modules\Core\Models\Branch;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

/**
 * The Billing cluster menu used to list five ungrouped Title Case report pages
 * first (so /billing opened on Daily Cash Closeout) and a second "Payments"
 * item for the refunds register.
 */
class BillingClusterNavigationTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->migrateModules(['Core', 'Billing']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $user = User::factory()->create(['branch_id' => Branch::factory()->create()->id]);
        $user->assignRole(Role::findOrCreate('super_admin', 'web'));
        $this->actingAs($user);
        Filament::setCurrentPanel(Filament::getPanel('corepanel'));
    }

    public function test_cluster_opens_on_invoices_and_groups_the_report_pages(): void
    {
        $groups = collect((new BillingCluster)->getCachedSubNavigation())
            ->mapWithKeys(fn (NavigationGroup $group): array => [
                (string) $group->getLabel() => collect($group->getItems())->map(fn (NavigationItem $item): string => $item->getLabel())->values()->all(),
            ]);

        $this->assertSame('Billing', $groups->keys()->first());
        $this->assertSame('Invoices', $groups->first()[0]);
        $this->assertSame(
            ['Revenue report', 'Daily cash closeout', 'Monthly revenue summary', 'Deposits & outstanding', 'Refunds & write-offs'],
            $groups->get('Reports'),
        );
        $this->assertContains('Refunds register', $groups->get('Billing'));
        $this->assertSame(1, collect($groups->get('Billing'))->filter(fn (string $label): bool => $label === 'Payments')->count());
    }

    public function test_refunds_register_has_a_clean_url(): void
    {
        $this->assertStringEndsWith('/billing/refunds-register', RefundsRegisterResource::getUrl());
    }
}
