<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Payments\Tables;

use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Filament\Actions\RefundPaymentAction;
use Modules\Billing\Models\Payment;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->filters([
                SelectFilter::make('branch_id')
                    ->label(__('Branch'))
                    ->relationship('branch', 'name')
                    ->preload()
                    ->searchable(),
                SelectFilter::make('patient_id')
                    ->label(__('Patient'))
                    ->relationship('patient', 'mrn')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record?->display_name)
                    ->preload()
                    ->searchable(),
            ])
            ->recordActions(self::recordActions())
            ->defaultSort('received_at', 'desc');
    }

    /**
     * @return array<int, TextColumn|CurrencyColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('id')->label('ID')->limit(8)->tooltip(fn (Payment $r) => $r->id),
            TextColumn::make('type')->badge(),
            TextColumn::make('method')->badge(),
            TextColumn::make('gateway'),
            CurrencyColumn::make('amount')
                ->currency(fn (Payment $record): string => (string) $record->currency),
            TextColumn::make('currency'),
            TextColumn::make('received_at')->dateTime()->sortable(),
        ];
    }

    /**
     * @return array<int, Action>
     */
    public static function recordActions(): array
    {
        return [
            Action::make('receipt')
                ->label(__('Receipt PDF'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->url(fn (Payment $record) => route('billing.payments.receipt', $record))
                ->openUrlInNewTab()
                ->visible(fn () => Auth::user()?->can('print_receipt')),
            Action::make('receipt_download')
                ->label(__('Download receipt'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(fn (Payment $record) => route('billing.payments.receipt', ['payment' => $record, 'download' => 1]))
                ->openUrlInNewTab()
                ->visible(fn () => Auth::user()?->can('download_receipt')),
            RefundPaymentAction::make()
                ->mountUsing(fn (Action $action, Payment $record) => $action->arguments(['payment_id' => $record->id]))
                ->visible(fn (Payment $record) => $record->type === PaymentType::Payment),
        ];
    }
}
