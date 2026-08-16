<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PatientDeposits\Schemas;

use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PatientDepositForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components(self::components());
    }

    /**
     * @return array<int, Component>
     */
    public static function components(): array
    {
        return [
            TextInput::make('amount')
                ->label(__('Amount'))
                ->numeric()
                ->disabled(),
            TextInput::make('unallocated_balance')
                ->label(__('Unallocated balance'))
                ->numeric()
                ->disabled(),
        ];
    }
}
