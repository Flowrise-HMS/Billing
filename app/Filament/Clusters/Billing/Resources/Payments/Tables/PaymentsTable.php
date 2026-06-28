<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Payments\Tables;

use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Fieldset;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Filament\Actions\RefundPaymentAction;
use Modules\Billing\Models\Payment;
use Modules\Core\Filament\Support\ClientIdentityColumn;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;
use Modules\Core\Support\Currency;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->filters(self::filters(), layout: FiltersLayout::AboveContent)
            ->recordActions(self::recordActions())
            ->defaultSort('received_at', 'desc');
    }

    /**
     * Eloquent query filters only — do not attach to array-backed `records()` tables.
     *
     * @return array<int, Filter|SelectFilter>
     */
    public static function filters(): array
    {
        return [
            Filter::make('received_at')

                ->columns(2)
                ->columnSpan(2)
                ->schema([
                    Fieldset::make()
                        ->columnSpanFull()
                        ->label(__('Received date'))->schema([
                            DateTimePicker::make('received_from')
                                ->label(__('From'))
                                ->placeholder(__('From date'))
                                ->native(false),
                            DateTimePicker::make('received_until')
                                ->label(__('Until'))
                                ->placeholder(__('To date'))
                                ->native(false),
                        ]),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['received_from'], fn (Builder $q, $date): Builder => $q->where('received_at', '>=', $date))
                        ->when($data['received_until'], fn (Builder $q, $date): Builder => $q->where('received_at', '<=', $date));
                }),
            Filter::make('created_at')
                ->columns(2)
                ->columnSpan(2)
                ->schema([
                    Fieldset::make()
                        ->columnSpanFull()
                        ->label(__('Created date'))->schema([
                            DateTimePicker::make('created_from')
                                ->label(__('From'))
                                ->placeholder(__('From date'))
                                ->native(false),
                            DateTimePicker::make('created_until')
                                ->label(__('Until'))
                                ->placeholder(__('To date'))
                                ->native(false),
                        ]),
                ])
                ->query(function (Builder $query, array $data): Builder {
                    return $query
                        ->when($data['created_from'], fn (Builder $q, $date): Builder => $q->where('created_at', '>=', $date))
                        ->when($data['created_until'], fn (Builder $q, $date): Builder => $q->where('created_at', '<=', $date));
                }),
            SelectFilter::make('recorded_by')
                ->label(__('Cashier'))
                ->relationship('recorder', 'name')
                ->preload()
                ->searchable(),
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
        ];
    }

    /**
     * @return array<int, TextColumn|CurrencyColumn>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('id')->label('ID')->limit(8)->tooltip(fn (Payment $r) => $r->id),
            ClientIdentityColumn::make(),
            TextColumn::make('recorder.name')
                ->label(__('Cashier'))
                ->sortable()
                ->placeholder(__('N/A')),
            TextColumn::make('type')->badge(),
            TextColumn::make('method')->badge(),
            TextColumn::make('gateway'),
            CurrencyColumn::make('amount')
                ->currency(fn (Payment $record): string => (string) $record->currency),
            TextColumn::make('currency'),
            TextColumn::make('received_at')->dateTime()->sortable(),
            TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, TextColumn|CurrencyColumn>
     */
    public static function reportColumns(): array
    {
        return [
            TextColumn::make('id')->label('ID')->limit(8)->tooltip(fn (Payment $r) => $r->id),
            ClientIdentityColumn::make(),
            TextColumn::make('recorder.name')
                ->label(__('Cashier'))
                ->sortable()
                ->placeholder(__('N/A')),
            TextColumn::make('type')->badge(),
            TextColumn::make('method')->badge(),
            TextColumn::make('gateway'),
            CurrencyColumn::make('amount')
                ->currency(fn (Payment $record): string => (string) $record->currency)
                ->summarize(
                    Sum::make()
                        ->label(__('Total'))
                        ->money(fn (): string => Currency::defaultCode(), locale: Currency::defaultLocale(), decimalPlaces: 2),
                ),
            TextColumn::make('currency'),
            TextColumn::make('received_at')->dateTime()->sortable(),
            TextColumn::make('created_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    /**
     * @return array<int, Action>
     */
    public static function recordActions(): array
    {
        return [
            Action::make('receipt')
                ->label(__('Receipt PDF'))
                ->icon(Heroicon::OutlinedDocumentArrowDown)
                ->url(fn (Payment $record) => route('billing.payments.receipt', $record))
                ->openUrlInNewTab()
                ->visible(fn () => Auth::user()?->can('print_receipt')),
            Action::make('receipt_download')
                ->label(__('Download receipt'))
                ->icon(Heroicon::OutlinedArrowDownTray)
                ->url(fn (Payment $record) => route('billing.payments.receipt', ['payment' => $record, 'download' => 1]))
                ->openUrlInNewTab()
                ->visible(fn () => Auth::user()?->can('download_receipt')),
            RefundPaymentAction::make()
                ->mountUsing(fn (Action $action, Payment $record) => $action->arguments(['payment_id' => $record->id]))
                ->visible(fn (Payment $record) => $record->type === PaymentType::Payment),
            ActionGroup::make([
                ViewAction::make(),
                DeleteAction::make(),
            ]),
        ];
    }
}
