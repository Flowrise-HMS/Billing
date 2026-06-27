<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Billing\Models\PaymentPlan;
use Modules\Core\Filament\Infolists\Components\CurrencyEntry;

class PaymentPlanInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Payment plan'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('invoice.invoice_number')->label(__('Invoice')),
                        TextEntry::make('client')
                            ->label(__('Client'))
                            ->state(fn (PaymentPlan $record): string => $record->invoice?->clientIdentity()->displayWithIdentifier() ?? 'N/A'),
                        CurrencyEntry::make('total_amount')
                            ->currency(fn (PaymentPlan $record): string => $record->invoice?->currency ?? 'GHS'),
                        CurrencyEntry::make('down_payment')
                            ->currency(fn (PaymentPlan $record): string => $record->invoice?->currency ?? 'GHS'),
                        TextEntry::make('installment_count')->label(__('Installments')),
                        TextEntry::make('frequency_days')
                            ->label(__('Frequency'))
                            ->formatStateUsing(fn ($state) => __(':days days', ['days' => $state])),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('start_date')->date(),
                        TextEntry::make('notes')
                            ->visible(fn ($state) => ! empty($state)),
                        CurrencyEntry::make('remainingBalance')
                            ->label(__('Remaining'))
                            ->currency(fn (PaymentPlan $record): string => $record->invoice?->currency ?? 'GHS')
                            ->state(fn (PaymentPlan $record): string => $record->remainingBalance()),
                    ]),
                Section::make(__('Installments'))
                    ->columnSpanFull()
                    ->schema(fn (PaymentPlan $record): array => $record->installments
                        ->sortBy('installment_number')
                        ->map(fn ($installment) => TextEntry::make("installment_{$installment->installment_number}")
                            ->label(__('Installment #:num', ['num' => $installment->installment_number]))
                            ->formatStateUsing(function () use ($installment): string {
                                $status = $installment->status->getLabel();
                                $due = $installment->due_date->format('Y-m-d');
                                $amount = number_format((float) $installment->amount, 2);
                                $paid = number_format((float) $installment->paid_amount, 2);

                                return "{$amount} ({$status}) — Due: {$due}, Paid: {$paid}";
                            })
                            ->badge()
                            ->color(fn () => $installment->status->getColor())
                        )
                        ->values()
                        ->all()),
            ]);
    }
}
