<?php

namespace Modules\Billing\Filament\Clusters\Billing\Widgets;

use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Components\Fieldset;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget as BaseWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentType;
use Modules\Billing\Filament\Clusters\Billing\BillingCluster;
use Modules\Billing\Filament\Support\SearchesPatients;
use Modules\Billing\Models\Payment;
use Modules\Core\Filament\Concerns\InteractsWithWidgetShield;
use Modules\Core\Filament\Support\ClientIdentityColumn;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class WriteOffsTableWidget extends BaseWidget
{
    use InteractsWithWidgetShield;
    use SearchesPatients;

    protected static ?string $cluster = BillingCluster::class;

    protected static bool $isDiscovered = false;

    protected int|string|array $columnSpan = 'full';

    public function table(Table $table): Table
    {
        return $table
            ->heading(__('Write-offs'))
            ->query(fn () => Payment::query()
                ->where('type', PaymentType::WriteOff)
                ->with([
                    'patient' => fn ($query) => $query->withoutGlobalScopes(),
                    'recorder',
                    'allocations.invoiceLine.invoice.patient' => fn ($query) => $query->withoutGlobalScopes(),
                ]))
            ->columns([
                TextColumn::make('received_at')
                    ->label(__('Date'))
                    ->dateTime()
                    ->sortable(),
                ClientIdentityColumn::make()
                    ->searchable(query: self::patientSearchQuery()),
                CurrencyColumn::make('amount')
                    ->label(__('Write-off amount'))
                    ->currency(fn (Payment $record): string => (string) $record->currency)
                    ->state(fn (Payment $record): float => abs((float) $record->amount)),
                TextColumn::make('metadata.reason')
                    ->label(__('Reason'))
                    ->placeholder(__('N/A'))
                    ->limit(40)
                    ->searchable(),
                TextColumn::make('method')
                    ->badge()
                    ->searchable(),
                TextColumn::make('gateway')
                    ->searchable(),
                TextColumn::make('recorder.name')
                    ->label(__('Recorded by'))
                    ->placeholder(__('N/A')),
                TextColumn::make('allocations.invoiceLine.invoice.invoice_number')
                    ->label(__('Invoice'))
                    ->placeholder(__('N/A')),
            ])
            ->filters([
                SelectFilter::make('branch_id')
                    ->label(__('Branch'))
                    ->relationship('branch', 'name')
                    ->preload()
                    ->searchable()
                    ->default(fn (): ?string => Auth::user()?->branch_id),
                SelectFilter::make('method')
                    ->label(__('Method'))
                    ->options(PaymentMethod::class)
                    ->attribute('method'),
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
            ], layout: FiltersLayout::AboveContentCollapsible)
            ->defaultSort('received_at', 'desc')
            ->paginated([10, 25, 50, 100])
            ->emptyStateHeading(__('No write-offs recorded'));
    }
}
