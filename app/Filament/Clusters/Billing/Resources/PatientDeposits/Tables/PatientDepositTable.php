<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PatientDeposits\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Modules\Billing\Enums\PatientDepositStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Models\PatientDeposit;
use Modules\Core\Filament\Support\ClientIdentityColumn;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;
use Modules\Core\Support\ClientIdentityResolver;

class PatientDepositTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->filters([
                SelectFilter::make('status')
                    ->label(__('Status'))
                    ->options(PatientDepositStatus::class)
                    ->attribute('status'),
                SelectFilter::make('method')
                    ->label(__('Method'))
                    ->options(PaymentMethod::class)
                    ->query(function (Builder $query, array $state): Builder {
                        return $query->when(
                            filled($state['value']),
                            fn (Builder $q) => $q->whereHas('payment', fn (Builder $p) => $p->where('method', $state['value']))
                        );
                    }),
            ])
            ->defaultSort('created_at', 'desc');
    }

    /**
     * @return array<int, TextColumn|CurrencyColumn|ClientIdentityColumn>
     */
    public static function columns(): array
    {
        return [
            ClientIdentityColumn::make(
                resolve: fn (PatientDeposit $record) => ClientIdentityResolver::resolve(
                    patientFullName: $record->patient?->full_name,
                    patientMrn: $record->patient?->mrn,
                ),
            ),
            CurrencyColumn::make('amount')
                ->currency(fn ($record): string => $record->currency ?? 'GHS'),
            TextColumn::make('payment.method')
                ->label(__('Method')),
            TextColumn::make('status')
                ->badge()
                ->sortable(),
            TextColumn::make('received_at')
                ->label(__('Received'))
                ->dateTime()
                ->sortable(),
            TextColumn::make('created_at')
                ->dateTime()
                ->sortable(),
        ];
    }
}
