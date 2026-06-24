<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Billing\Enums\PaymentPlanStatus;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\PaymentPlanResource;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\PaymentPlanService;
use Modules\Core\Filament\Tables\Columns\CurrencyColumn;

class PaymentPlansRelationManager extends RelationManager
{
    protected static string $relationship = 'paymentPlan';

    protected static ?string $title = 'Payment plans';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Payment plans');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns([
                CurrencyColumn::make('total_amount')
                    ->currency(fn ($record) => $record->invoice?->currency ?? 'GHS'),
                TextColumn::make('installment_count')->label(__('Installments')),
                TextColumn::make('frequency_days')
                    ->label(__('Frequency'))
                    ->formatStateUsing(fn ($state) => __(':days days', ['days' => $state])),
                TextColumn::make('status')->badge(),
                TextColumn::make('start_date')->date(),
                TextColumn::make('created_at')->dateTime(),
            ])
            ->headerActions([
                CreateAction::make('createPlan')
                    ->label(__('Create payment plan'))
                    ->icon('heroicon-o-plus')
                    ->form([
                        TextInput::make('down_payment')
                            ->label(__('Down payment'))
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->required(),
                        TextInput::make('installment_count')
                            ->label(__('Number of installments'))
                            ->numeric()
                            ->default(3)
                            ->minValue(1)
                            ->required(),
                        TextInput::make('frequency_days')
                            ->label(__('Frequency (days)'))
                            ->numeric()
                            ->default(30)
                            ->minValue(1)
                            ->required(),
                        Textarea::make('notes')
                            ->label(__('Notes'))
                            ->rows(2)
                            ->nullable(),
                    ])
                    ->action(function (array $data, RelationManager $livewire): void {
                        $invoice = $livewire->getOwnerRecord();
                        if (! $invoice instanceof Invoice) {
                            return;
                        }

                        $service = app(PaymentPlanService::class);
                        $plan = $service->createPlan(
                            invoice: $invoice,
                            installmentCount: (int) $data['installment_count'],
                            frequencyDays: (int) $data['frequency_days'],
                            downPayment: (string) ($data['down_payment'] ?? '0'),
                            notes: $data['notes'] ?? null,
                        );

                        Notification::make()
                            ->success()
                            ->title(__('Payment plan created with :count installments.', ['count' => $plan->installments->count()]))
                            ->send();
                    })
                    ->visible(function (RelationManager $livewire): bool {
                        $invoice = $livewire->getOwnerRecord();

                        return $invoice
                            && ! in_array($invoice->status->value, ['draft', 'void', 'paid'])
                            && bccomp($invoice->balanceDue(), '0', 2) > 0
                            && ! $invoice->paymentPlan()->where('status', PaymentPlanStatus::Active)->exists();
                    }),
            ])
            ->recordActions([
                Action::make('view')
                    ->label(__('View'))
                    ->icon('heroicon-o-eye')
                    ->url(fn ($record) => PaymentPlanResource::getUrl('view', ['record' => $record])),
            ])
            ->defaultSort('created_at', 'desc');
    }
}
