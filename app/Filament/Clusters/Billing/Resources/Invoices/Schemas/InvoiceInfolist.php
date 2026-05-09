<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InvoiceInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Invoice'))
                    ->schema([
                        TextEntry::make('invoice_number'),
                        TextEntry::make('status'),
                        TextEntry::make('currency'),
                        TextEntry::make('total'),
                        TextEntry::make('amount_paid'),
                        TextEntry::make('issued_at'),
                    ]),
            ]);
    }
}
