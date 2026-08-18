<?php

namespace Modules\Billing\Filament\Clusters\Billing\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Widgets\OutstandingReceivablesTableWidget;
use Modules\Billing\Filament\Clusters\Billing\Widgets\PatientDepositBalancesTableWidget;

class DepositsAndOutstanding extends Page
{
    use HasPageShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedWallet;

    protected string $view = 'billing::filament.clusters.billing.pages.deposits-and-outstanding';

    /**
     * @return array<int, class-string>
     */
    public function getTableWidgets(): array
    {
        return [
            PatientDepositBalancesTableWidget::class,
            OutstandingReceivablesTableWidget::class,
        ];
    }
}
