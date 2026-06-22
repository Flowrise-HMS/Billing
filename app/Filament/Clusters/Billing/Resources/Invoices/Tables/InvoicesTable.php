<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Tables;

use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\InvoiceType;
use Modules\Billing\Filament\Actions\RecordInvoicePaymentAction;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;
use Modules\Billing\Models\Invoice;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class InvoicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('#')->rowIndex(),
                TextColumn::make('invoice_number')->copyable()->searchable()->sortable(),
                TextColumn::make('patient.display_name')->label(__('Patient')),
                TextColumn::make('invoice_type')
                    ->label(__('Type'))
                    ->badge()
                    ->sortable(),
                TextColumn::make('status')
                    ->label(__('Status'))
                    ->badge()
                    ->sortable(),
                CurrencyColumn::make('total')
                    ->currency(fn (Invoice $record): string => (string) $record->currency)
                    ->sortable(),
                CurrencyColumn::make('amount_paid')
                    ->currency(fn (Invoice $record): string => (string) $record->currency)
                    ->sortable(),
                CurrencyColumn::make('balance_due')
                    ->label(__('Balance due'))
                    ->currency(fn (Invoice $record): string => (string) $record->currency)
                    ->sortable(query: fn ($query, $direction) => $query->orderBy('total', $direction))
                    ->color(fn ($record) => bccomp($record->balanceDue(), '0', 2) > 0 ? 'danger' : 'success')
                    ->getStateUsing(fn ($record) => $record?->balanceDue() ?? 0),
                TextColumn::make('issued_at')->dateTime()->sortable(),
            ])
            ->filters([
                Filter::make('issued_at')
                    ->label(__('Issue date'))
                    ->columns(2)
                    ->columnSpan(2)
                    ->schema([
                        DateTimePicker::make('issued_from')
                            ->label(__('From'))
                            ->placeholder(__('From date'))
                            ->native(false),
                        DateTimePicker::make('issued_until')
                            ->label(__('Until'))
                            ->placeholder(__('To date'))
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['issued_from'], fn (Builder $q, $date): Builder => $q->where('issued_at', '>=', $date))
                            ->when($data['issued_until'], fn (Builder $q, $date): Builder => $q->where('issued_at', '<=', $date));
                    }),
                Filter::make('created_at')
                    ->label(__('Created date'))
                    ->columns(2)
                    ->columnSpan(2)
                    ->schema([
                        DateTimePicker::make('created_from')
                            ->label(__('From'))
                            ->placeholder(__('From date'))
                            ->native(false),
                        DateTimePicker::make('created_until')
                            ->label(__('Until'))
                            ->placeholder(__('To date'))
                            ->native(false),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return $query
                            ->when($data['created_from'], fn (Builder $q, $date): Builder => $q->where('created_at', '>=', $date))
                            ->when($data['created_until'], fn (Builder $q, $date): Builder => $q->where('created_at', '<=', $date));
                    }),
                SelectFilter::make('invoice_type')
                    ->label(__('Type'))
                    ->options(InvoiceType::class)
                    ->multiple(),
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(InvoiceStatus::class)
                    ->multiple(),
                SelectFilter::make('branch_id')
                    ->label(__('Branch'))
                    ->relationship('branch', 'name')
                    ->preload()
                    ->searchable(),
                SelectFilter::make('patient_id')
                    ->label(__('Patient'))
                    ->relationship('patient', 'mrn')
                    ->getOptionLabelFromRecordUsing(fn ($record) => $record?->display_name)
                    ->preload()
                    ->searchable(),
            ], layout: FiltersLayout::AboveContent)
            ->recordActions([
                RecordInvoicePaymentAction::make()
                    ->mountUsing(function ($action, $record): void {
                        $action->arguments(['invoice_id' => $record?->id]);
                    })
                    ->visible(fn ($record) => ! empty($record) && ! in_array($record?->status, [InvoiceStatus::Draft, InvoiceStatus::Void], true)
                        && bccomp($record?->balanceDue(), '0', 2) > 0),

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
                ViewAction::make()->url(fn ($record) => InvoiceResource::getUrl('view', ['record' => $record])),
                DeleteAction::make(),
                Action::make('activities')
                    ->label('Activities')
                    ->icon('heroicon-o-bell-alert')
                    ->url(fn ($record) => InvoiceResource::getUrl('activities', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
