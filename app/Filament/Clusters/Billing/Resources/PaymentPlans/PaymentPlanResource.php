<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans;

use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Enums\PaymentPlanStatus;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Pages\CreatePaymentPlan;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Pages\ListPaymentPlans;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Pages\ViewPaymentPlan;
use Modules\Billing\Models\PaymentPlan;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class PaymentPlanResource extends Resource
{
    protected static ?string $model = PaymentPlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $cluster = BillingCluster::class;

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('invoice.invoice_number')
                    ->label(__('Invoice'))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('invoice.patient.display_name')
                    ->label(__('Patient'))
                    ->searchable(),
                CurrencyColumn::make('total_amount')
                    ->currency(fn (PaymentPlan $record): string => $record->invoice?->currency ?? 'GHS'),
                TextColumn::make('installment_count')
                    ->label(__('Installments')),
                TextColumn::make('frequency_days')
                    ->label(__('Frequency'))
                    ->formatStateUsing(fn ($state) => __(':days days', ['days' => $state])),
                TextColumn::make('status')
                    ->badge()
                    ->sortable(),
                TextColumn::make('start_date')
                    ->date()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(PaymentPlanStatus::class)
                    ->attribute('status'),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPaymentPlans::route('/'),
            'create' => CreatePaymentPlan::route('/create'),
            'view' => ViewPaymentPlan::route('/{record}'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery();
    }
}
