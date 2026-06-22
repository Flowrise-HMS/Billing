<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Billing\Enums\InvoiceType;
use Modules\Core\Support\Currency;
use Modules\Patient\Models\Patient;

class InvoiceForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Invoice'))
                    ->schema([
                        Select::make('patient_id')
                            ->label(__('Patient'))
                            ->relationship(
                                name: 'patient',
                                titleAttribute: 'mrn',
                                modifyQueryUsing: fn ($query) => $query
                                    ->when(auth()->user()?->branch_id, fn ($q, $branchId) => $q->where('branch_id', $branchId))
                            )
                            ->getOptionLabelFromRecordUsing(fn (Patient $record): string => $record->full_name.' ('.$record->mrn.')')
                            ->searchable(['mrn', 'first_name', 'last_name'])
                            ->preload()
                            ->required(),
                        Select::make('invoice_type')
                            ->label(__('Invoice type'))
                            ->options(collect(InvoiceType::cases())->mapWithKeys(
                                fn (InvoiceType $t) => [$t->value => $t->getLabel()]
                            )->all())
                            ->default(InvoiceType::Standalone->value)
                            ->required(),
                        TextInput::make('currency')
                            ->label(__('Currency'))
                            ->length(3)
                            ->maxLength(3)
                            ->default(fn () => Currency::defaultCode())
                            ->required(),
                    ]),
            ]);
    }
}
