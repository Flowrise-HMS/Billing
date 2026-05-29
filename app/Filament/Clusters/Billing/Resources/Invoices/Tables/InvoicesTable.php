<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Tables;

use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Filament\Actions\RecordInvoicePaymentAction;
use Modules\Billing\Models\Invoice;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('#')->rowIndex(),
                TextColumn::make('invoice_number')->copyable()->searchable()->sortable(),
                TextColumn::make('patient.mrn')->label('MRN')->searchable(),
                TextColumn::make('patient.display_name')->label(__('Patient'))->searchable(),
                TextColumn::make('status')->badge()->sortable(),
                TextColumn::make('total')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('amount_paid')->numeric(decimalPlaces: 2)->sortable(),
                TextColumn::make('balance_due')
                    ->label(__('Balance due'))
                    ->numeric(decimalPlaces: 2)
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('total', $direction))
                    ->color(fn ($record) => bccomp($record->balanceDue(), '0', 2) > 0 ? 'danger' : 'success')
                    ->getStateUsing(fn ($record) => $record?->balanceDue() ?? 0),
                TextColumn::make('issued_at')->dateTime()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(InvoiceStatus::class)
                    ->multiple(),
                SelectFilter::make('branch_id')
                    ->label(__('Branch'))
                    ->relationship('branch','name')
                    ->preload()
                    ->searchable(),
                SelectFilter::make('patient_id')
                    ->label(__('Patient'))
                    ->relationship('patient','mrn')
                    ->getOptionLabelFromRecordUsing(fn($record) => $record?->display_name)
                    ->preload()
                    ->searchable(),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                RecordInvoicePaymentAction::make()
                    ->visible(fn ($record) => !empty($record) && ! in_array($record?->status, [InvoiceStatus::Draft, InvoiceStatus::Void], true)
                        && bccomp($record?->balanceDue(), '0', 2) > 0),
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
            ->defaultSort('issued_at', 'asc');
    }
}
