<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Schemas;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PaymentPlan;
use Modules\Billing\Models\PaymentPlanInstallment;

class PaymentPlanForm
{
    public static function configure(Schema $schema, bool $includeInvoicePicker = true): Schema
    {
        $fields = $includeInvoicePicker
            ? array_merge(self::invoicePickerFields(), self::planFields())
            : self::planFields();

        return $schema
            ->components([
                Section::make(__('Payment plan'))
                    ->columnSpanFull()
                    ->schema($fields),
            ]);
    }

    /**
     * @return array<int, Component>
     */
    public static function planFields(): array
    {
        return [
            TextInput::make('down_payment')
                ->label(__('Down payment'))
                ->numeric()
                ->default(0)
                ->minValue(0)
                ->required(),
            TextInput::make('installment_count')
                ->label(__('Number of installments'))
                ->numeric()
                ->default(3)
                ->minValue(1)
                ->required(),
            TextInput::make('frequency_days')
                ->label(__('Frequency (days)'))
                ->numeric()
                ->default(30)
                ->minValue(1)
                ->required(),
            Textarea::make('notes')
                ->label(__('Notes'))
                ->rows(2)
                ->nullable(),
        ];
    }

    /**
     * @return array<int, Component>
     */
    public static function invoicePickerFields(): array
    {
        return [
            Select::make('invoice_id')
                ->label(__('Invoice'))
                ->relationship('invoice', 'invoice_number')
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, callable $set): void {
                    if (! $state) {
                        return;
                    }

                    $invoice = Invoice::query()->find($state);
                    if ($invoice) {
                        $set('total_amount', (string) $invoice->balanceDue());
                    }
                }),
            TextInput::make('total_amount')
                ->label(__('Invoice balance'))
                ->disabled()
                ->dehydrated(false)
                ->numeric(),
        ];
    }

    /**
     * @return array<int, Component>
     */
    public static function collectInstallmentFields(PaymentPlan $plan): array
    {
        return [
            Select::make('installment_id')
                ->label(__('Installment'))
                ->options(
                    $plan->installments
                        ->reject(fn (PaymentPlanInstallment $i) => $i->isFullyPaid())
                        ->mapWithKeys(fn (PaymentPlanInstallment $i) => [
                            $i->id => __('Installment #:num — :amount', [
                                'num' => $i->installment_number,
                                'amount' => number_format((float) $i->remainingAmount(), 2),
                            ]),
                        ])
                )
                ->required(),
            TextInput::make('amount')
                ->label(__('Amount'))
                ->numeric()
                ->minValue(0.01)
                ->required(),
            Select::make('method')
                ->label(__('Payment method'))
                ->options(PaymentMethod::class)
                ->default(PaymentMethod::Cash->value)
                ->required(),
            TextInput::make('reference')
                ->label(__('Reference'))
                ->maxLength(255)
                ->nullable(),
        ];
    }
}
