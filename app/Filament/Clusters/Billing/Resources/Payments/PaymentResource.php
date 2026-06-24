<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Payments;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\Pages\ListPayments;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\Pages\ViewPayment;
use Modules\Billing\Models\Payment;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class PaymentResource extends Resource
{
    protected static ?string $model = Payment::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static ?string $cluster = BillingCluster::class;

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns(static::getPaymentTableColumns())
            ->filters([
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
            ])
            ->recordActions(static::getPaymentRecordActions())
            ->defaultSort('received_at', 'desc');
    }

    public static function getPaymentTableColumns(): array
    {
        return [
            TextColumn::make('id')->label('ID')->limit(8)->tooltip(fn (Payment $r) => $r->id),
            TextColumn::make('method')->badge(),
            TextColumn::make('gateway'),
            CurrencyColumn::make('amount')
                ->currency(fn (Payment $record): string => (string) $record->currency),
            TextColumn::make('currency'),
            TextColumn::make('received_at')->dateTime()->sortable(),
        ];
    }

    public static function getPaymentRecordActions(): array
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
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPayments::route('/'),
            'view' => ViewPayment::route('/{record}'),
        ];
    }

    public static function getRelations(): array
    {
        return [
            \Modules\Billing\Filament\Clusters\Billing\Resources\Payments\RelationManagers\PaymentAllocationsRelationManager::class,
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
