<?php

namespace Modules\Billing\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Filament\Schemas\ApplyDepositForm;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Models\PatientDeposit;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\DepositApplicationService;
use Modules\Billing\Services\DepositBalanceService;

class ApplyDepositAction
{
    public static function make(): Action
    {
        return Action::make('applyDeposit')
            ->label(__('Apply deposit'))
            ->icon(Heroicon::OutlinedWallet)
            ->color('info')
            ->modalHeading(__('Apply deposit to invoice'))
            ->modalWidth('2xl')
            ->hidden(fn () => ! Auth::user()?->can('Create Payment'))
            ->visible(function (?Model $record = null, array $arguments = []): bool {
                $invoiceId = $record?->getKey() ?? $arguments['invoice_id'] ?? null;
                if (! $invoiceId) {
                    return false;
                }

                $invoice = Invoice::query()->find($invoiceId);
                if (! $invoice || in_array($invoice->status, [InvoiceStatus::Draft, InvoiceStatus::Void], true)) {
                    return false;
                }

                if (bccomp($invoice->balanceDue(), '0', 2) <= 0) {
                    return false;
                }

                return app(DepositBalanceService::class)
                    ->activeDepositsForPatient((string) $invoice->patient_id, (string) $invoice->branch_id)
                    ->isNotEmpty();
            })
            ->schema(function (?Model $record = null, array $arguments = []): array {
                $invoice = Invoice::with(['lines' => fn ($q) => $q->orderBy('id')])
                    ->findOrFail($record?->getKey() ?? $arguments['invoice_id']);

                $deposits = app(DepositBalanceService::class)
                    ->activeDepositsForPatient((string) $invoice->patient_id, (string) $invoice->branch_id);

                return ApplyDepositForm::components($invoice, $deposits);
            })
            ->action(function (array $data, array $arguments, DepositApplicationService $service): ?Payment {
                $invoiceId = $arguments['invoice_id'] ?? $data['invoice_id'] ?? null;
                if (! $invoiceId) {
                    Notification::make()->danger()->title(__('Invoice not specified.'))->send();

                    return null;
                }

                $invoice = Invoice::with(['lines' => fn ($q) => $q->orderBy('id')])
                    ->findOrFail($invoiceId);

                $userBranchId = Context::get('current_branch_id', Auth::user()?->branch_id);
                if ($userBranchId !== null && (string) $userBranchId !== (string) $invoice->branch_id) {
                    Notification::make()->danger()->title(__('Invoice belongs to a different branch.'))->send();

                    return null;
                }

                $deposit = PatientDeposit::query()->find($data['patient_deposit_id'] ?? null);
                if (! $deposit) {
                    Notification::make()->danger()->title(__('Deposit not found.'))->send();

                    return null;
                }

                $mode = $data['payment_mode'] ?? 'full';
                $lineIds = null;

                if ($mode === 'selected') {
                    [$amount, $lineIds] = static::resolveSelectedAmount($data['line_items'] ?? [], $invoice, $deposit);
                } elseif ($mode === 'amount') {
                    $amount = (string) ($data['amount'] ?? '0');
                } else {
                    $amount = bccomp($deposit->remainingAmount(), $invoice->balanceDue(), 2) <= 0
                        ? $deposit->remainingAmount()
                        : $invoice->balanceDue();
                }

                try {
                    $payment = $service->apply(
                        deposit: $deposit,
                        invoice: $invoice,
                        amount: $amount,
                        lineIds: $lineIds,
                        recordedBy: Auth::id(),
                    );
                } catch (\InvalidArgumentException $e) {
                    Notification::make()->danger()->title($e->getMessage())->send();

                    return null;
                }

                $invoice->refresh();
                Notification::make()
                    ->success()
                    ->title(__('Deposit applied'))
                    ->body(__('Applied :amount. Remaining invoice balance: :remaining', [
                        'amount' => number_format((float) $payment->amount, 2),
                        'remaining' => number_format((float) $invoice->balanceDue(), 2),
                    ]))
                    ->send();

                return $payment;
            })
            ->model(Payment::class);
    }

    /**
     * @return array{0: string, 1: string[]}
     */
    private static function resolveSelectedAmount(array $lineItems, Invoice $invoice, PatientDeposit $deposit): array
    {
        $lines = $invoice->lines->keyBy('id');
        $selectedIds = [];
        $total = '0';

        foreach ($lineItems as $item) {
            $lineId = $item['line_id'] ?? null;
            $itemAmount = $item['amount'] ?? '0';

            if (! $lineId || ! isset($lines[$lineId]) || bccomp((string) $itemAmount, '0', 2) <= 0) {
                continue;
            }

            $line = $lines[$lineId];
            $remaining = $line->remainingAmount();
            $apply = bccomp((string) $itemAmount, $remaining, 2) > 0 ? $remaining : (string) $itemAmount;
            $selectedIds[] = (string) $lineId;
            $total = bcadd($total, $apply, 2);
        }

        $max = bccomp($deposit->remainingAmount(), $invoice->balanceDue(), 2) <= 0
            ? $deposit->remainingAmount()
            : $invoice->balanceDue();

        if (bccomp($total, $max, 2) > 0) {
            $total = $max;
        }

        return [$total, $selectedIds];
    }
}
