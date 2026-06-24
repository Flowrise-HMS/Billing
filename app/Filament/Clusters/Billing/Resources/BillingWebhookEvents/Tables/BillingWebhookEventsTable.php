<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\BillingWebhookEvents\Tables;

use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\PaymentResource;
use Modules\Billing\Models\BillingWebhookEvent;

class BillingWebhookEventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('driver')
                    ->badge()
                    ->sortable(),
                TextColumn::make('idempotency_key')
                    ->label('Idempotency key')
                    ->limit(20)
                    ->copyable()
                    ->searchable(),
                TextColumn::make('payment.id')
                    ->label('Payment')
                    ->formatStateUsing(fn ($state) => $state ? substr((string) $state, 0, 8).'…' : '—')
                    ->url(fn (BillingWebhookEvent $record) => $record->payment_id
                        ? PaymentResource::getUrl(
                            'view',
                            ['record' => $record->payment_id],
                        )
                        : null),
                TextColumn::make('processed_at')
                    ->dateTime()
                    ->sortable()
                    ->color(fn ($state) => $state ? 'success' : 'warning')
                    ->icon(fn ($state) => $state ? 'heroicon-o-check-circle' : 'heroicon-o-clock'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('driver')
                    ->label(__('Driver'))
                    ->options(fn (): array => BillingWebhookEvent::query()
                        ->distinct()
                        ->pluck('driver', 'driver')
                        ->toArray()
                    )
                    ->multiple(),
                SelectFilter::make('processed')
                    ->label(__('Processed'))
                    ->options([
                        'yes' => __('Processed'),
                        'no' => __('Pending'),
                    ])
                    ->query(fn ($query, array $data) => match ($data['value'] ?? null) {
                        'yes' => $query->whereNotNull('processed_at'),
                        'no' => $query->whereNull('processed_at'),
                        default => $query,
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->searchable();
    }
}
