<?php

namespace Modules\Billing\Filament\Schemas;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Modules\Billing\Enums\PaymentMethod;

class RecordDepositForm
{
    /**
     * @return array<int, Component>
     */
    public static function components(?string $defaultPatientId = null): array
    {
        return [
            Select::make('patient_id')
                ->label(__('Patient'))
                ->relationship('patient', 'display_name')
                ->searchable()
                ->preload()
                ->default($defaultPatientId)
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
        ];
    }
}
