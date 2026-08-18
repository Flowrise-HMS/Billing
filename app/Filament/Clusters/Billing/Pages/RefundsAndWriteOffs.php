<?php

namespace Modules\Billing\Filament\Clusters\Billing\Pages;

use BezhanSalleh\FilamentShield\Traits\HasPageShield;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Widgets\RefundsTableWidget;
use Modules\Billing\Filament\Clusters\Billing\Widgets\WriteOffsTableWidget;

class RefundsAndWriteOffs extends Page
{
    use HasPageShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentMinus;

    protected string $view = 'billing::filament.clusters.billing.pages.refunds-and-write-offs';

    /**
     * @return array<int, class-string>
     */
    public function getTableWidgets(): array
    {
        return [
            RefundsTableWidget::class,
            WriteOffsTableWidget::class,
        ];
    }
}
