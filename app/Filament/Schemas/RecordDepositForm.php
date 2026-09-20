<?php

namespace Modules\Billing\Filament\Schemas;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Modules\Billing\Support\PaymentMethodOptions;
use Modules\Patient\Models\Patient;

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
                ->relationship('patient', 'mrn')
                ->getOptionLabelFromRecordUsing(fn (Patient $record): string => $record->full_name.' ('.$record->mrn.')')
                ->searchable(['mrn', 'first_name', 'last_name'])
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
                ->options(fn (): array => PaymentMethodOptions::options())
                ->default(fn (): string => PaymentMethodOptions::default())
                ->required(),
            TextInput::make('reference')
                ->label(__('Reference'))
                ->maxLength(255)
                ->nullable(),
        ];
    }
}
