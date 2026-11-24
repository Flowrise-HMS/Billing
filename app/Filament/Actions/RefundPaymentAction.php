<?php

namespace Modules\Billing\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Models\Payment;
use Modules\Billing\Policies\PaymentPolicy;
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
            ->hidden(fn () => ! app(PaymentPolicy::class)->create(Auth::user()))
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

                try {
                    $service->refund($payment, $refundAmount, $data['reason'] ?? null, Auth::id());
                } catch (\InvalidArgumentException $e) {
                    Notification::make()
                        ->danger()
                        ->title(__('Refund exceeds payment amount'))
                        ->body($e->getMessage())
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('Refund recorded'))
                    ->body(__(':amount refunded.', ['amount' => number_format((float) $refundAmount, 2)]))
                    ->send();
            });
    }
}
