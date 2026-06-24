<?php

namespace Modules\Billing\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Models\Payment;
use Modules\Billing\Services\PaymentRecordingService;

class RefundPaymentAction
{
    public static function make(): Action
    {
        return Action::make('refund')
            ->label(__('Refund'))
            ->icon(Heroicon::OutlinedArrowUturnLeft)
            ->color('danger')
            ->modalHeading(__('Issue refund'))
            ->modalWidth('lg')
            ->hidden(fn () => ! Auth::user()?->can('Create Payment'))
            ->schema([
                TextInput::make('amount')
                    ->label(__('Refund amount'))
                    ->numeric()
                    ->minValue(0.01)
                    ->required()
                    ->helperText(fn ($record) => __('Max refund: :amount', ['amount' => number_format((float) ($record?->amount ?? 0), 2)])),
                TextInput::make('reason')
                    ->label(__('Reason'))
                    ->maxLength(255),
            ])
            ->action(function (array $data, array $arguments, PaymentRecordingService $service): void {
                $paymentId = $arguments['payment_id'] ?? null;
                $payment = Payment::with('allocations')->find($paymentId);

                if (! $payment) {
                    return;
                }

                $refundAmount = (string) $data['amount'];

                if (bccomp($refundAmount, '0', 2) <= 0) {
                    return;
                }

                if (bccomp($refundAmount, (string) $payment->amount, 2) > 0) {
                    Notification::make()
                        ->danger()
                        ->title(__('Refund exceeds payment amount'))
                        ->send();

                    return;
                }

                $totalAllocated = '0';
                foreach ($payment->allocations as $alloc) {
                    $totalAllocated = bcadd($totalAllocated, (string) $alloc->amount, 2);
                }

                $allocations = [];
                foreach ($payment->allocations as $alloc) {
                    $ratio = bcdiv((string) $alloc->amount, $totalAllocated, 10);
                    $lineRefund = bcsub('0', bcmul($ratio, $refundAmount, 2), 2);
                    $allocations[(string) $alloc->invoice_line_id] = $lineRefund;
                }

                $service->record(
                    allocations: $allocations,
                    method: PaymentMethod::Gateway,
                    gateway: $payment->gateway,
                    currency: $payment->currency,
                    patientId: $payment->patient_id,
                    branchId: (string) $payment->branch_id,
                    recordedBy: Auth::id(),
                    metadata: [
                        'source' => 'filament',
                        'action' => 'refund',
                        'original_payment_id' => $payment->id,
                        'reason' => $data['reason'] ?? '',
                    ],
                    type: PaymentType::Refund,
                );

                Notification::make()
                    ->success()
                    ->title(__('Refund recorded'))
                    ->body(__(':amount refunded.', ['amount' => number_format((float) $refundAmount, 2)]))
                    ->send();
            });
    }
}
