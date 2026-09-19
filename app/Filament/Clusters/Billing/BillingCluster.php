<?php

namespace Modules\Billing\Filament\Clusters\Billing;

use BackedEnum;
use Filament\Clusters\Cluster;
use Filament\Support\Icons\Heroicon;
use Modules\Core\Enums\SidebarGroup;

class BillingCluster extends Cluster
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|\UnitEnum|null $navigationGroup = SidebarGroup::Finance;

    protected static ?int $navigationSort = 10;
}
