<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Core\Filament\Infolists\Components\CurrencyEntry;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Invoice'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('invoice_number'),
                        TextEntry::make('status'),
                        TextEntry::make('currency'),
                        CurrencyEntry::make('total')
                            ->currency(fn ($record) => (string) $record->currency),
                        CurrencyEntry::make('amount_paid')
                            ->currency(fn ($record) => (string) $record->currency),
                        TextEntry::make('issued_at'),
                        TextEntry::make('due_at')->dateTime(),
                    ]),

            ]);
    }
}
