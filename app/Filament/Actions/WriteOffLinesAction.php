<?php

namespace Modules\Billing\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Services\PaymentRecordingService;

class WriteOffLinesAction
{
    public static function make(): Action
    {
        return Action::make('writeOff')
            ->label(__('Write off'))
            ->icon(Heroicon::OutlinedDocumentMinus)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading(__('Write off line'))
            ->modalDescription(__('This will adjust the remaining balance to zero (no money exchanged).'))
            ->hidden(fn () => ! Auth::user()?->can('Create Payment'))
            ->action(function (array $arguments, PaymentRecordingService $service): void {
                $lineId = $arguments['line_id'] ?? null;
                if (! $lineId) {
                    return;
                }

                $line = \Modules\Billing\Models\InvoiceLine::query()
                    ->whereKey($lineId)
                    ->with('invoice')
                    ->first();

                if (! $line) {
                    return;
                }

                $remaining = $line->remainingAmount();
                if (bccomp($remaining, '0', 2) <= 0) {
                    Notification::make()
                        ->warning()
                        ->title(__('Nothing to write off'))
                        ->send();

                    return;
                }

                $service->record(
                    allocations: [(string) $line->id => $remaining],
                    method: PaymentMethod::Cash,
                    gateway: 'write_off',
                    currency: $line->invoice->currency,
                    patientId: $line->invoice->patient_id,
                    branchId: (string) $line->invoice->branch_id,
                    recordedBy: Auth::id(),
                    metadata: ['source' => 'filament', 'action' => 'write_off'],
                    type: PaymentType::WriteOff,
                );

                Notification::make()
                    ->success()
                    ->title(__('Line written off'))
                    ->send();
            });
    }
}
