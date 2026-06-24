<?php

namespace Modules\Billing\Filament\Actions;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Filament\Schemas\RecordDepositForm;
use Modules\Billing\Services\DepositRecordingService;

class RecordDepositAction
{
    public static function make(): Action
    {
        return Action::make('recordDeposit')
            ->label(__('Record deposit'))
            ->icon(Heroicon::OutlinedBanknotes)
            ->color('info')
            ->modalHeading(__('Record patient deposit'))
            ->modalWidth('lg')
            ->hidden(fn () => ! Auth::user()?->can('Create Payment'))
            ->schema(fn (?array $arguments = []): array => RecordDepositForm::components(
                $arguments['patient_id'] ?? null
            ))
            ->action(function (array $data, DepositRecordingService $deposits): void {
                $deposits->record(
                    patientId: $data['patient_id'],
                    branchId: Auth::user()?->branch_id ?? throw new \RuntimeException('No branch context.'),
                    amount: (string) $data['amount'],
                    method: $data['method'],
                    reference: $data['reference'] ?? null,
                    recordedBy: Auth::id(),
                );

                Notification::make()
                    ->success()
                    ->title(__('Deposit recorded'))
                    ->send();
            });
    }
}
