<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Livewire\Attributes\Reactive;
use Modules\Billing\Data\BillingReportCriteria;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\Tables\PaymentsTable;
use Modules\Billing\Models\Payment;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;

class RecentPaymentsTableWidget extends BaseWidget
{
    use InteractsWithWidgetShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    #[Reactive]
    public ?string $startDate = null;

    #[Reactive]
    public ?string $endDate = null;

    #[Reactive]
    public ?string $branchId = null;

    #[Reactive]
    public ?string $paymentMethod = null;

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Recent payments'))
            ->query(fn () => Payment::queryForReportListing($this->reportCriteria()))
            ->columns(PaymentsTable::reportColumns())
            ->filters(PaymentsTable::filters(), layout: FiltersLayout::AboveContentCollapsible)
            ->summaries(pageCondition: false)
            ->recordActions(PaymentsTable::recordActions())
            ->defaultSort('received_at', 'desc')
            ->paginated([10, 50, 100, 150, 200, 500])
            ->emptyStateHeading(__('No payments in this period'));
    }

    protected function reportCriteria(): BillingReportCriteria
    {
        return BillingReportCriteria::fromRequest([
            'preset' => 'custom',
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'branch_id' => $this->branchId,
            'payment_method' => $this->paymentMethod,
        ]);
    }
}
