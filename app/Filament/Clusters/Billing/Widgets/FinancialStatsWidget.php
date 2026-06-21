<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Widgets\StatsOverviewWidget as BaseWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\DB;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Pages\BillingDesk;
use Modules\Billing\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;

class FinancialStatsWidget extends BaseWidget
{
    use InteractsWithWidgetShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static bool $isDiscovered = true;

    protected function getStats(): array
    {
        $branchId = Context::get('current_branch_id', Auth::user()?->branch_id);

        $todayRevenue = Payment::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->whereDate('received_at', today())
            ->sum('amount');

        $thisWeekRevenue = Payment::when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('received_at', '>=', now()->startOfWeek())
            ->sum('amount');

        $unpaidBalance = Invoice::query()
            ->when($branchId, fn ($q) => $q->where('branch_id', $branchId))
            ->where('status', '!=', InvoiceStatus::Void)
            ->sum(DB::raw('total - amount_paid'));

        $currency = config('core.default_currency');

        return [
            Stat::make("Today's Revenue", $currency.' '.number_format($todayRevenue, 2))
                ->description('Total payments received today')
                ->descriptionIcon('heroicon-m-banknotes')
                ->color('success'),
            Stat::make('Weekly Revenue', $currency.' '.number_format($thisWeekRevenue, 2))
                ->description('Payments received this week')
                ->descriptionIcon('heroicon-m-chart-bar'),
            Stat::make('Total Unpaid', $currency.' '.number_format($unpaidBalance, 2))
                ->description('Outstanding invoice balances — click to view')
                ->descriptionIcon('heroicon-m-exclamation-circle')
                ->color('danger')
                ->url(BillingDesk::getUrl()),
        ];
    }
}
