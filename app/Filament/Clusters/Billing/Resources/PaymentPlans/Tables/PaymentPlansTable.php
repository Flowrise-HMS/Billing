<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Billing\Enums\PaymentPlanStatus;
use Modules\Billing\Models\PaymentPlan;
use Modules\Core\Filament\Support\ClientIdentityColumn;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class PaymentPlansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(PaymentPlanStatus::class)
                    ->attribute('status'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<int, TextColumn|CurrencyColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('invoice.invoice_number')
                ->label(__('Invoice'))
                ->searchable()
                ->sortable(),
            ClientIdentityColumn::make(
                resolve: fn (PaymentPlan $record) => $record->invoice?->clientIdentity(),
            ),
            CurrencyColumn::make('total_amount')
                ->currency(fn (PaymentPlan $record): string => $record->invoice?->currency ?? 'GHS'),
            TextColumn::make('installment_count')
                ->label(__('Installments')),
            TextColumn::make('frequency_days')
                ->label(__('Frequency'))
                ->formatStateUsing(fn ($state) => __(':days days', ['days' => $state])),
            TextColumn::make('status')
                ->badge()
                ->sortable(),
            TextColumn::make('start_date')
                ->date()
                ->sortable(),
            TextColumn::make('created_at')
                ->dateTime()
                ->sortable(),
        ];
    }
}
