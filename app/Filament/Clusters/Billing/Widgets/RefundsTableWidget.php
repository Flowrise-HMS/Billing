<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\RefundsRegister\Tables\RefundsRegisterTable;
use Modules\Billing\Models\Payment;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;

class RefundsTableWidget extends BaseWidget
{
    use InteractsWithWidgetShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return RefundsRegisterTable::configure(
            $table
                ->heading(__('Refunds'))
                ->query(fn () => Payment::query()
                    ->where('type', PaymentType::Refund)
                    ->with([
                        'patient' => fn ($query) => $query->withoutGlobalScopes(),
                        'recorder',
                        'allocations.invoiceLine.invoice.patient' => fn ($query) => $query->withoutGlobalScopes(),
                    ]))
                ->paginated([10, 25, 50, 100])
                ->emptyStateHeading(__('No refunds recorded')),
            defaultToUserBranch: true,
        );
    }
}
