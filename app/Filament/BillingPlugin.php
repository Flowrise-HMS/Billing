<?php

namespace Modules\Billing\Filament;

use Coolsam\Modules\Concerns\ModuleFilamentPlugin;
use Filament\Contracts\Plugin;
use Filament\Panel;

class BillingPlugin implements Plugin
{
    use ModuleFilamentPlugin;

    public function getModuleName(): string
    {
        return 'Billing';
    }

    public function getId(): string
    {
        return 'billing';
    }

    public function boot(Panel $panel): void {}
}
