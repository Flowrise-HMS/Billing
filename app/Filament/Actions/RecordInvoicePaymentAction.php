<?php

namespace Modules\Billing\Filament\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Repeater\TableColumn;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
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
            ->hidden(fn () => ! Auth::user()?->can('Create Payment'))
            ->fillForm(fn (?Model $record, array $arguments): array => self::formFillState($record, $arguments))
            ->schema(fn (?Model $record = null, array $arguments = []): array => self::formSchema($record, $arguments))
            ->action(function (array $data, ?Model $record, array $arguments, PaymentRecordingService $payments, InvoiceAllocationBuilder $builder): ?Payment {
                $invoiceId = self::resolveInvoiceId($record, $arguments) ?? $data['invoice_id'] ?? null;
                if (! $invoiceId) {
                    Notification::make()->danger()->title(__('Invoice not specified.'))->send();

                    return null;
                }

                $invoice = Invoice::with(['lines' => fn ($q) => $q->orderBy('id')])
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

    /**
     * @return array<string, mixed>
     */
    public static function formFillState(?Model $record, array $arguments = []): array
    {
        $invoiceId = self::resolveInvoiceId($record, $arguments);

        if ($invoiceId === null) {
            return [];
        }

        $invoice = self::findInvoiceForForm($invoiceId);

        if ($invoice === null) {
            return [];
        }

        $preSelectedLineId = $arguments['line_id'] ?? null;

        return [
            'invoice_id' => $invoiceId,
            'payment_mode' => $preSelectedLineId ? 'selected' : 'full',
            'amount' => $invoice->balanceDue(),
            'line_items' => self::payableLineItemsForInvoice($invoice, $preSelectedLineId),
        ];
    }

    /**
     * @return list<array{line_id: string, description: string, balance: string, amount: string, selected: bool}>
     */
    public static function payableLineItemsForInvoice(Invoice $invoice, ?string $onlyLineId = null): array
    {
        $items = $invoice->lines
            ->filter(fn ($line) => $line->line_status !== InvoiceLineStatus::Void
                && bccomp($line->remainingAmount(), '0', 2) > 0);

        if ($onlyLineId !== null) {
            $items = $items->where('id', $onlyLineId);
        }

        return $items->values()->map(fn ($line): array => [
            'line_id' => (string) $line->id,
            'description' => sprintf('%s (qty: %s)', $line->description, $line->quantity),
            'balance' => number_format((float) $line->remainingAmount(), 2),
            'amount' => (string) $line->remainingAmount(),
            'selected' => true,
        ])->all();
    }

    /**
     * @return array<int, mixed>
     */
    private static function formSchema(?Model $record, array $arguments): array
    {
        $invoiceId = self::resolveInvoiceId($record, $arguments);
        $preSelectedLineId = $arguments['line_id'] ?? null;

        return [
            Hidden::make('invoice_id')
                ->default($invoiceId),

            ToggleButtons::make('payment_mode')
                ->label(__('Payment mode'))
                ->options([
                    'full' => __('Pay full invoice'),
                    'amount' => __('Pay amount'),
                    'selected' => __('Pay selected items'),
                ])
                ->default($preSelectedLineId ? 'selected' : 'full')
                ->inline()
                ->live()
                ->afterStateUpdated(function (?string $state, callable $set, Get $get) use ($preSelectedLineId): void {
                    $invoice = self::findInvoiceForForm($get('invoice_id'));

                    if ($invoice === null) {
                        return;
                    }

                    $set('amount', $invoice->balanceDue());

                    if ($state === 'selected') {
                        $set('line_items', self::payableLineItemsForInvoice($invoice, $preSelectedLineId));
                    }
                }),

            TextInput::make('amount')
                ->label(__('Amount to pay'))
                ->numeric()
                ->disabled(fn (Get $get): bool => $get('payment_mode') !== 'amount')
                ->required(fn (Get $get): bool => $get('payment_mode') === 'amount')
                ->rules([
                    fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get): void {
                        if ($get('payment_mode') !== 'amount') {
                            return;
                        }

                        $invoice = self::findInvoiceForForm($get('invoice_id'));

                        if ($invoice === null) {
                            return;
                        }

                        if (bccomp((string) $value, '0', 2) <= 0) {
                            $fail(__('Amount must be greater than zero.'));

                            return;
                        }

                        if (bccomp((string) $value, $invoice->balanceDue(), 2) > 0) {
                            $fail(__('Amount exceeds invoice balance of :balance.', [
                                'balance' => $invoice->balanceDue(),
                            ]));
                        }
                    },
                ]),

            Repeater::make('line_items')
                ->label(__('Line items'))
                ->visible(fn (Get $get): bool => $get('payment_mode') === 'selected')
                ->table([
                    TableColumn::make(__('Pay'))->width('4rem'),
                    TableColumn::make(__('Item')),
                    TableColumn::make(__('Balance'))->width('6rem'),
                    TableColumn::make(__('Pay amount'))->width('7rem'),
                ])
                ->compact()
                ->schema([
                    Checkbox::make('selected')
                        ->label(__('Pay'))
                        ->default(true)
                        ->live(),
                    Hidden::make('line_id'),
                    TextInput::make('description')
                        ->label(__('Item'))
                        ->disabled()
                        ->dehydrated(false)
                        ->columnSpan(2),
                    TextInput::make('balance')
                        ->label(__('Balance'))
                        ->disabled()
                        ->dehydrated(false),
                    TextInput::make('amount')
                        ->label(__('Pay amount'))
                        ->numeric()
                        ->minValue(0)
                        ->disabled(fn (Get $get): bool => ! $get('selected'))
                        ->required(fn (Get $get): bool => (bool) $get('selected'))
                        ->rules([
                            static fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                                if ((float) ($value ?? 0) < 0) {
                                    $fail(__('Amount cannot be negative.'));
                                }
                            },
                        ]),
                ])
                ->addable(false)
                ->deletable(false)
                ->reorderable(false),

            Grid::make(2)->schema([
                Select::make('payment_method')
                    ->label(__('Payment method'))
                    ->options(
                        collect(PaymentMethod::cases())
                            ->reject(fn ($method) => $method === PaymentMethod::Gateway)
                            ->mapWithKeys(fn ($method) => [$method->value => $method->getLabel()])
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
                ->visible(fn (Get $get): bool => $get('payment_method') === PaymentMethod::Cash->value),
        ];
    }

    private static function findInvoiceForForm(?string $invoiceId): ?Invoice
    {
        if ($invoiceId === null || $invoiceId === '') {
            return null;
        }

        return Invoice::with(['lines' => fn ($query) => $query->orderBy('id')])
            ->find($invoiceId);
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
            if (! ($item['selected'] ?? false)) {
                continue;
            }

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

    private static function resolveInvoiceId(?Model $record, array $arguments): ?string
    {
        if ($record instanceof Invoice) {
            return (string) $record->getKey();
        }

        if (isset($arguments['invoice_id']) && $arguments['invoice_id'] !== '') {
            return (string) $arguments['invoice_id'];
        }

        return null;
    }
}
