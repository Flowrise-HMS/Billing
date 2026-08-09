<?php

namespace Modules\Billing\Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Livewire\Livewire;
use Modules\Billing\Filament\Clusters\Billing\Pages\ManageBillingSettings;
use Modules\Billing\Settings\BillingSettings;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class ManageBillingSettingsTest extends TestCase
{
    use DatabaseTransactions;

    protected function setUp(): void
    {
        parent::setUp();

        $this->migrateModules(['Core', 'Billing']);
    }

    private function adminWithSettingsAccess(): User
    {
        Permission::findOrCreate('View ManageBillingSettings', 'web');

        return User::factory()->create()->givePermissionTo('View ManageBillingSettings');
    }

    public function test_manage_billing_settings_lives_in_billing_namespace(): void
    {
        $this->assertSame(
            'Modules\\Billing\\Filament\\Clusters\\Billing\\Pages\\ManageBillingSettings',
            ManageBillingSettings::class,
        );

        $this->assertSame(
            'Modules\\Billing\\Settings\\BillingSettings',
            BillingSettings::class,
        );
    }

    public function test_billing_settings_form_fields_are_present_and_persist(): void
    {
        $admin = $this->adminWithSettingsAccess();

        Livewire::actingAs($admin)
            ->test(ManageBillingSettings::class)
            ->assertFormFieldExists('auto_invoice_on_checkin')
            ->assertFormFieldExists('financial_hold_enabled')
            ->assertFormFieldExists('sms_enabled')
            ->fillForm([
                'auto_invoice_on_checkin' => false,
                'financial_hold_enabled' => true,
                'sms_enabled' => false,
            ])
            ->call('save');

        $settings = app(BillingSettings::class);

        $this->assertFalse($settings->auto_invoice_on_checkin);
        $this->assertTrue($settings->financial_hold_enabled);
        $this->assertFalse($settings->sms_enabled);
    }
}
