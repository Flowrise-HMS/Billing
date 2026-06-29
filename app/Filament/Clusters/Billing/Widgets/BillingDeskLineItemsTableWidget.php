<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Actions\Action;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Reactive;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\InvoiceLine;
use Modules\Billing\Models\PaymentAllocation;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;

class BillingDeskLineItemsTableWidget extends BaseWidget
{
    use InteractsWithWidgetShield;

    protected static ?string $cluster = BillingCluster::class;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    #[Reactive]
    public ?string $invoiceId = null;

    public function table(Table $table): Table
    {
        $currency = $this->resolveCurrency();

        return $table
            ->query(fn (): Builder => $this->lineItemsQuery())
            ->columns([
                TextColumn::make('description')
                    ->label(__('Item')),
                TextColumn::make('quantity')
                    ->label(__('Qty')),
                TextColumn::make('line_total')
                    ->label(__('Total'))
                    ->money($currency)
                    ->alignEnd(),
                TextColumn::make('amount_paid')
                    ->label(__('Paid'))
                    ->money($currency)
                    ->alignEnd(),
                TextColumn::make('balance')
                    ->label(__('Balance'))
                    ->alignEnd()
                    ->getStateUsing(fn (InvoiceLine $record): string => $record->remainingAmount())
                    ->formatStateUsing(fn (string $state): string => number_format((float) $state, 2))
                    ->color(fn (string $state): string => (float) $state > 0 ? 'danger' : 'success'),
            ])
            ->recordActions([
                Action::make('print_receipt')
                    ->label(__('Print receipt'))
                    ->icon('heroicon-m-printer')
                    ->color('gray')
                    ->url(fn (InvoiceLine $record): string => $this->receiptUrl($record))
                    ->openUrlInNewTab()
                    ->visible(fn (InvoiceLine $record): bool => $this->latestPaymentId($record) !== null),
            ])
            ->paginated(false)
            ->defaultSort('id');
    }

    protected function lineItemsQuery(): Builder
    {
        if (blank($this->invoiceId)) {
            return InvoiceLine::query()->whereRaw('0 = 1');
        }

        return InvoiceLine::query()
            ->where('invoice_id', $this->invoiceId)
            ->where('line_status', '!=', InvoiceLineStatus::Void)
            ->with('paymentAllocations.payment')
            ->orderBy('id');
    }

    protected function resolveCurrency(): string
    {
        if (blank($this->invoiceId)) {
            return 'GHS';
        }

        return (string) (Invoice::query()->whereKey($this->invoiceId)->value('currency') ?? 'GHS');
    }

    protected function latestPaymentId(InvoiceLine $record): ?string
    {
        return $record->paymentAllocations
            ->sortByDesc(fn (PaymentAllocation $allocation) => $allocation->payment?->created_at)
            ->first()?->payment_id;
    }

    protected function receiptUrl(InvoiceLine $record): string
    {
        $paymentId = $this->latestPaymentId($record);

        return route('billing.payments.receipt', $paymentId).'?line_id='.$record->id;
    }
}
