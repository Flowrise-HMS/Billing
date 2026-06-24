<?php

namespace Modules\Billing\Filament\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Models\Payment;

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
            ->schema(fn (?array $arguments = []): array => [
                Select::make('patient_id')
                    ->label(__('Patient'))
                    ->relationship('patient', 'display_name')
                    ->searchable()
                    ->preload()
                    ->default($arguments['patient_id'] ?? null)
                    ->required(),
                TextInput::make('amount')
                    ->label(__('Deposit amount'))
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
            ])
            ->action(function (array $data): void {
                Payment::query()->create([
                    'patient_id' => $data['patient_id'],
                    'branch_id' => Auth::user()?->branch_id ?? throw new \RuntimeException('No branch context.'),
                    'method' => $data['method'],
                    'gateway' => $data['method'],
                    'type' => PaymentType::Deposit,
                    'amount' => $data['amount'],
                    'currency' => 'GHS',
                    'provider_transaction_id' => $data['reference'] ?? (string) Str::uuid(),
                    'received_at' => now(),
                    'recorded_by' => Auth::id(),
                    'metadata' => ['source' => 'filament', 'action' => 'deposit'],
                ]);

                Notification::make()
                    ->success()
                    ->title(__('Deposit recorded'))
                    ->send();
            });
    }
}
