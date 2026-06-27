<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Schemas;

use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Modules\Billing\Models\Invoice;
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
                        TextEntry::make('client')
                            ->label(__('Client'))
                            ->state(fn (Invoice $record): string => $record->clientIdentity()->displayWithIdentifier()),
                        TextEntry::make('status'),
                        TextEntry::make('currency'),
                        CurrencyEntry::make('total')
                            ->currency(fn ($record) => (string) $record->currency),
                        CurrencyEntry::make('amount_paid')
                            ->currency(fn ($record) => (string) $record->currency),
                        CurrencyEntry::make('balance_due')
                            ->label(__('Balance due'))
                            ->currency(fn ($record) => (string) $record->currency)
                            ->getStateUsing(fn (Invoice $record): string => $record->balanceDue())
                            ->color(fn (string $state): string => (float) $state > 0 ? 'danger' : 'success'),
                        TextEntry::make('due_at')
                            ->dateTime()
                            ->badge()
                            ->color(fn (?string $state, Invoice $record): string => $record->isOverdue() ? 'danger' : 'gray')
                            ->formatStateUsing(fn (?string $state, Invoice $record): string => $record->isOverdue()
                                ? __('Overdue: :date', ['date' => $state])
                                : ($state ?? '—')
                            ),
                        TextEntry::make('issued_at'),
                    ]),

            ]);
    }
}
