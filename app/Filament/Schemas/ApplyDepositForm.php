<?php

namespace Modules\Billing\Filament\Schemas;

use Closure;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Illuminate\Support\Collection;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PatientDeposit;

class ApplyDepositForm
{
    /**
     * @param  Collection<int, PatientDeposit>  $deposits
     * @return array<int, Component>
     */
    public static function components(Invoice $invoice, Collection $deposits): array
    {
        $currency = $invoice->currency ?? 'GHS';

        $depositOptions = $deposits->mapWithKeys(fn (PatientDeposit $d) => [
            $d->id => sprintf(
                '%s — %s %s remaining',
                $d->created_at->format('Y-m-d'),
                $currency,
                number_format((float) $d->remainingAmount(), 2),
            ),
        ])->all();

        return [
            Select::make('patient_deposit_id')
                ->label(__('Deposit'))
                ->options($depositOptions)
                ->default($deposits->first()?->id)
                ->required()
                ->live(),

            ToggleButtons::make('payment_mode')
                ->label(__('Application mode'))
                ->options([
                    'full' => __('Pay full invoice'),
                    'amount' => __('Pay amount'),
                    'selected' => __('Pay selected items'),
                ])
                ->default('full')
                ->inline()
                ->live(),

            TextInput::make('amount')
                ->label(__('Amount to apply'))
                ->numeric()
                ->required()
                ->visible(fn (Get $get) => $get('payment_mode') === 'amount')
                ->rules([
                    'min:0.01',
                    static function (Get $get) use ($invoice, $deposits): Closure {
                        return static function (string $attribute, $value, Closure $fail) use ($get, $invoice, $deposits): void {
                            $depositId = $get('patient_deposit_id');
                            $deposit = $deposits->firstWhere('id', $depositId);
                            $maxDeposit = $deposit?->remainingAmount() ?? '0';
                            $maxInvoice = $invoice->balanceDue();
                            $max = bccomp($maxDeposit, $maxInvoice, 2) <= 0 ? $maxDeposit : $maxInvoice;

                            if (bccomp((string) $value, $max, 2) > 0) {
                                $fail(__('Amount exceeds maximum of :max.', ['max' => $max]));
                            }
                        };
                    },
                ]),

            Repeater::make('line_items')
                ->label(__('Line items'))
                ->visible(fn (Get $get) => $get('payment_mode') === 'selected')
                ->schema([
                    Hidden::make('line_id'),
                    TextInput::make('description')
                        ->label(__('Item'))
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpan(2),
                    TextInput::make('balance')
                        ->label(__('Balance ('.$currency.')'))
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('amount')
                        ->label(__('Apply amount'))
                        ->numeric()
                        ->minValue(0)
                        ->default(0),
                ])
                ->columns(4)
                ->defaultItems(0)
                ->addable(false)
                ->deletable(false)
                ->reorderable(false)
                ->afterStateHydrated(static function (callable $set) use ($invoice): void {
                    $items = $invoice->lines
                        ->filter(fn ($l) => $l->line_status !== InvoiceLineStatus::Void
                            && bccomp($l->remainingAmount(), '0', 2) > 0);

                    $set('line_items', $items->values()->map(fn ($l) => [
                        'line_id' => $l->id,
                        'description' => sprintf('%s (qty: %s)', $l->description, $l->quantity),
                        'balance' => number_format((float) $l->remainingAmount(), 2),
                        'amount' => (string) $l->remainingAmount(),
                    ])->toArray());
                }),

            Grid::make(1)->schema([
                TextInput::make('reference')
                    ->label(__('Reference / Note'))
                    ->maxLength(255),
            ]),
        ];
    }
}
