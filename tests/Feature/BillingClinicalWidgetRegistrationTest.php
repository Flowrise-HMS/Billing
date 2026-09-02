<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Filament\Pages\Page;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Modules\Billing\Filament\Widgets\PatientBillingSummaryWidget;
use Modules\Core\Classes\Support\PageWidgetsRegistry;
use Modules\Core\Support\ModuleAvailability;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class BillingClinicalWidgetRegistrationTest extends TestCase
{
    use DatabaseTransactions;

    private const WORKSPACE = 'Modules\\Clinical\\Filament\\Clusters\\Workspace\\Pages\\ClinicalWorkspace';

    protected function setUp(): void
    {
        parent::setUp();

        if (! ModuleAvailability::billingEnabled() || ! ModuleAvailability::clinicalEnabled()) {
            $this->markTestSkipped('Billing and Clinical modules must be enabled.');
        }

        $this->migrateModules(['Core', 'Patient', 'Billing']);

        Permission::findOrCreate('view_patient_balance', 'web');
    }

    #[Test]
    public function it_contributes_the_billing_card_to_the_patient_banner_for_permitted_users(): void
    {
        $this->actingAs(User::factory()->create()->givePermissionTo('view_patient_balance'));

        $widgets = app(PageWidgetsRegistry::class)
            ->for(self::WORKSPACE, 'patient_banner', $this->createStub(Page::class));

        $this->assertContains(PatientBillingSummaryWidget::class, $widgets);
    }

    #[Test]
    public function it_contributes_nothing_without_the_permission(): void
    {
        $this->actingAs(User::factory()->create());

        $widgets = app(PageWidgetsRegistry::class)
            ->for(self::WORKSPACE, 'patient_banner', $this->createStub(Page::class));

        $this->assertSame([], $widgets);
    }
}
