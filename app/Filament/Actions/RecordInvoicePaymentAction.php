<?php

namespace Modules\Billing\Filament\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\InvoiceLineStatus;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\InvoiceAllocationBuilder;
use Modules\Billing\Services\PaymentRecordingService;

class RecordInvoicePaymentAction
{
    public static function make(): Action
    {
        return Action::make('collectPayment')
            ->label(__('Collect payment'))
            ->icon(Heroicon::OutlinedCurrencyDollar)
            ->color('success')
            ->modalHeading(__('Collect payment'))
            ->modalWidth('2xl')
            ->authorize('create', Payment::class)
            ->schema(function (?Invoice $record = null, array $arguments = []): array {
                $invoice = $record ?? Invoice::with(['lines' => fn ($q) => $q->orderBy('id')])
                    ->findOrFail($arguments['invoice_id'] ?? abort(500));

                $currency = $invoice->currency ?? 'GHS';
                $preSelectedLineId = $arguments['line_id'] ?? null;

                return [
                    ToggleButtons::make('payment_mode')
                        ->label(__('Payment mode'))
                        ->options([
                            'full' => __('Pay full invoice'),
                            'amount' => __('Pay amount'),
                            'selected' => __('Pay selected items'),
                        ])
                        ->default($preSelectedLineId ? 'selected' : 'full')
                        ->inline()
                        ->live(),

                    TextInput::make('amount')
                        ->label(__('Amount to pay'))
                        ->numeric()
                        ->required()
                        ->visible(fn (Get $get) => $get('payment_mode') === 'amount')
                        ->rules([
                            'min:0.01',
                            static fn () => static function (string $attribute, $value, Closure $fail) use ($invoice) {
                                if (bccomp((string) $value, $invoice->balanceDue(), 2) > 0) {
                                    $fail(__('Amount exceeds invoice balance of :balance.', [
                                        'balance' => $invoice->balanceDue(),
                                    ]));
                                }
                            },
                        ]),

                    Repeater::make('line_items')
                        ->label(__('Line items'))
                        ->visible(fn (Get $get) => $get('payment_mode') === 'selected')
                        ->schema([
                            \Filament\Forms\Components\Hidden::make('line_id'),
                            TextInput::make('description')
                                ->label(__('Item'))
                                ->disabled()
                                ->dehydrated(false)
                                ->columnSpan(2),
                            TextInput::make('balance')
                                ->label(__('Balance (' . $currency . ')'))
                                ->disabled()
                                ->dehydrated(false),
                            TextInput::make('amount')
                                ->label(__('Pay amount'))
                                ->numeric()
                                ->minValue(0)
                                ->default(0)
                                ->rules([
                                    static fn () => static function (string $attribute, $value, Closure $fail) {
                                        if ((float) ($value ?? 0) < 0) {
                                            $fail(__('Amount cannot be negative.'));
                                        }
                                    },
                                ]),
                        ])
                        ->columns(4)
                        ->defaultItems(0)
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->afterStateHydrated(static function (callable $set) use ($invoice, $preSelectedLineId) {
                            $items = $invoice->lines
                                ->filter(fn ($l) => $l->line_status !== InvoiceLineStatus::Void
                                    && bccomp($l->remainingAmount(), '0', 2) > 0);

                            if ($preSelectedLineId) {
                                $items = $items->where('id', $preSelectedLineId);
                            }

                            $set('line_items', $items->values()->map(fn ($l) => [
                                'line_id' => $l->id,
                                'description' => sprintf('%s (qty: %s)', $l->description, $l->quantity),
                                'balance' => number_format((float) $l->remainingAmount(), 2),
                                'amount' => (string) $l->remainingAmount(),
                            ])->toArray());
                        }),

                    Grid::make(2)->schema([
                        Select::make('payment_method')
                            ->label(__('Payment method'))
                            ->options(
                                collect(PaymentMethod::cases())
                                    ->reject(fn ($m) => $m === PaymentMethod::Gateway)
                                    ->mapWithKeys(fn ($m) => [$m->value => $m->getLabel()])
                            )
                            ->default(PaymentMethod::Cash->value)
                            ->required()
                            ->live(),

                        TextInput::make('reference')
                            ->label(__('Reference / Note'))
                            ->maxLength(255),
                    ]),

                    TextInput::make('amount_tendered')
                        ->label(__('Amount tendered'))
                        ->numeric()
                        ->minValue(0)
                        ->visible(fn (Get $get) => $get('payment_method') === PaymentMethod::Cash->value),
                ];
            })
            ->action(function (array $data, ?Invoice $record = null, array $arguments = [], PaymentRecordingService $payments, InvoiceAllocationBuilder $builder): ?Payment {
                $invoiceId = $record?->id ?? $arguments['invoice_id'] ?? $data['invoice_id'] ?? null;
                if (! $invoiceId) {
                    Notification::make()->danger()->title(__('Invoice not specified.'))->send();
                    return null;
                }

                $invoice = $record ?? Invoice::with(['lines' => fn ($q) => $q->orderBy('id')])
                    ->findOrFail($invoiceId);

                if ($invoice->status === InvoiceStatus::Void) {
                    Notification::make()->danger()->title(__('Cannot collect payment on void invoices.'))->send();
                    return null;
                }

                if (bccomp($invoice->balanceDue(), '0', 2) <= 0) {
                    Notification::make()->danger()->title(__('Invoice has no outstanding balance.'))->send();
                    return null;
                }

                $userBranchId = Context::get('current_branch_id', Auth::user()?->branch_id);
                if ($userBranchId !== null && (string) $userBranchId !== (string) $invoice->branch_id) {
                    Notification::make()->danger()->title(__('Invoice belongs to a different branch.'))->send();
                    return null;
                }

                $mode = $data['payment_mode'] ?? 'full';

                $allocations = match ($mode) {
                    'amount' => $builder->allocateAmountAcrossUnpaidLines($invoice, (string) ($data['amount'] ?? '0')),
                    'selected' => static::buildSelectedAllocations($data['line_items'] ?? [], $invoice),
                    default => static::buildFullAllocations($invoice),
                };

                if (empty($allocations)) {
                    Notification::make()
                        ->danger()
                        ->title(__('No valid allocations to record.'))
                        ->send();
                    return null;
                }

                $methodEnum = PaymentMethod::tryFrom($data['payment_method'] ?? 'cash') ?? PaymentMethod::Cash;

                $metadata = ['source' => 'billing_desk'];
                if (! empty($data['reference'])) {
                    $metadata['reference'] = $data['reference'];
                }
                if (! empty($data['amount_tendered'])) {
                    $totalAmount = '0';
                    foreach ($allocations as $amt) {
                        $totalAmount = bcadd($totalAmount, (string) $amt, 2);
                    }
                    $metadata['amount_tendered'] = (string) $data['amount_tendered'];
                    $metadata['change_due'] = bcsub((string) $data['amount_tendered'], $totalAmount, 2);
                }

                $payment = $payments->record(
                    allocations: $allocations,
                    method: $methodEnum,
                    gateway: $methodEnum->value,
                    currency: $invoice->currency,
                    patientId: $invoice->patient_id,
                    branchId: (string) $invoice->branch_id,
                    recordedBy: Auth::id(),
                    metadata: $metadata,
                );

                $currency = $invoice->currency ?? 'GHS';
                $remainingAfter = bcsub($invoice?->balanceDue(), (string) $payment->amount, 2);

                Notification::make()
                    ->success()
                    ->title(__('Payment recorded'))
                    ->body(__(':currency :amount collected. Remaining balance: :remaining', [
                        'currency' => $currency,
                        'amount' => number_format((float) $payment->amount, 2),
                        'remaining' => number_format((float) $remainingAfter, 2),
                    ]))
                    ->actions([
                        Action::make('print_receipt')
                            ->label(__('Print receipt'))
                            ->button()
                            ->url(route('billing.payments.receipt', $payment))
                            ->openUrlInNewTab(),
                    ])
                    ->send();

                return $payment;
            })
            ->model(Payment::class);
    }

    private static function buildFullAllocations(Invoice $invoice): array
    {
        $allocations = [];
        foreach ($invoice->lines as $line) {
            if ($line->line_status === InvoiceLineStatus::Void) {
                continue;
            }
            $remaining = $line->remainingAmount();
            if (bccomp($remaining, '0', 2) > 0) {
                $allocations[(string) $line->id] = $remaining;
            }
        }
        return $allocations;
    }

    private static function buildSelectedAllocations(array $lineItems, Invoice $invoice): array
    {
        $lines = $invoice->lines->keyBy('id');
        $allocations = [];

        foreach ($lineItems as $item) {
            $lineId = $item['line_id'] ?? null;
            $amount = $item['amount'] ?? '0';

            if (! $lineId || ! isset($lines[$lineId])) {
                continue;
            }

            if (bccomp((string) $amount, '0', 2) <= 0) {
                continue;
            }

            $line = $lines[$lineId];
            $remaining = $line->remainingAmount();

            if (bccomp((string) $amount, $remaining, 2) > 0) {
                $amount = $remaining;
            }

            $allocations[(string) $lineId] = (string) $amount;
        }

        return $allocations;
    }
}
