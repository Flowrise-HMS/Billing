<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Enums\InvoiceStatus;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice_number')->searchable()->sortable(),
                TextColumn::make('patient.mrn')->label('MRN')->searchable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('total')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('amount_paid')->numeric(decimalPlaces: 2),
                TextColumn::make('issued_at')->dateTime()->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('invoice_pdf')
                    ->label(__('Invoice PDF'))
                    ->icon(Heroicon::OutlinedDocumentArrowDown)
                    ->url(fn ($record) => route('billing.invoices.pdf', $record))
                    ->openUrlInNewTab()
                    ->visible(fn () => Auth::user()?->can('view_invoice_pdf') || Auth::user()?->can('print_invoice')),
                Action::make('invoice_pdf_download')
                    ->label(__('Download PDF'))
                    ->icon(Heroicon::OutlinedArrowDownTray)
                    ->url(fn ($record) => route('billing.invoices.pdf', ['invoice' => $record, 'download' => 1]))
                    ->openUrlInNewTab()
                    ->visible(fn () => Auth::user()?->can('download_invoice')),
                EditAction::make()
                    ->visible(fn ($record) => $record->status === InvoiceStatus::Draft),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
