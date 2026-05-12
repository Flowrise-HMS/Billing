<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use BezhanSalleh\FilamentShield\Traits\HasWidgetShield;
use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Enums\InvoiceStatus;

class FinancialStatsWidget extends BaseWidget
{
    use HasWidgetShield;

    protected static ?string $cluster = BillingCluster::class;

    protected function getStats(): array
    {
        $todayRevenue = Payment::whereDate('received_at', today())->sum('amount');
        $thisWeekRevenue = Payment::where('received_at', '>=', now()->startOfWeek())->sum('amount');

        $unpaidBalance = Invoice::query()
            ->where('status', '!=', InvoiceStatus::Void)
            ->sum(DB::raw('total - amount_paid'));

        $currency = config('core.default_currency');

        return [
            Stat::make("Today's Revenue", $currency . ' ' . number_format($todayRevenue, 2))
                ->description('Total payments received today')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Weekly Revenue', $currency . ' ' . number_format($thisWeekRevenue, 2))
                ->description('Payments received this week')
                ->descriptionIcon('heroicon-m-chart-bar'),
            Stat::make('Total Unpaid', $currency . ' ' . number_format($unpaidBalance, 2))
                ->description('Outstanding invoice balances')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('danger'),
        ];
    }
}
