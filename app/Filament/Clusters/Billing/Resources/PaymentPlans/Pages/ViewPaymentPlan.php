<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\Section;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Schema;
use Illuminate\Support\Facades\Auth;
use Modules\Billing\Enums\PaymentPlanStatus;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\PaymentPlanResource;
use Modules\Billing\Models\PaymentPlan;
use Modules\Billing\Services\PaymentPlanService;
use Modules\Core\Filament\Infolists\Components\CurrencyEntry;

class ViewPaymentPlan extends ViewRecord
{
    protected static string $resource = PaymentPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('cancel')
                ->label(__('Cancel plan'))
                ->color('danger')
                ->icon('heroicon-o-no-symbol')
                ->visible(fn () => $this->getRecord()->status === PaymentPlanStatus::Active)
                ->requiresConfirmation()
                ->action(function (PaymentPlanService $service): void {
                    $service->cancelPlan($this->getRecord());
                    Notification::make()->success()->title(__('Payment plan cancelled.'))->send();
                    $this->redirect(static::getUrl(['record' => $this->getRecord()]));
                }),
            Action::make('collectInstallment')
                ->label(__('Collect installment'))
                ->color('success')
                ->icon('heroicon-o-currency-dollar')
                ->visible(fn () => $this->getRecord()->status === PaymentPlanStatus::Active)
                ->form([
                    Select::make('installment_id')
                        ->label(__('Installment'))
                        ->options(fn (PaymentPlan $record) => $record->installments
                            ->reject(fn ($i) => $i->isFullyPaid())
                            ->mapWithKeys(fn ($i) => [$i->id => __('Installment #:num — :amount', [
                                'num' => $i->installment_number,
                                'amount' => number_format((float) $i->remainingAmount(), 2),
                            ])])
                        )
                        ->required(),
                    TextInput::make('amount')
                        ->label(__('Amount'))
                        ->numeric()
                        ->minValue(0.01)
                        ->required(),
                    Select::make('method')
                        ->label(__('Payment method'))
                        ->options(\Modules\Billing\Enums\PaymentMethod::class)
                        ->default(\Modules\Billing\Enums\PaymentMethod::Cash->value)
                        ->required(),
                    TextInput::make('reference')
                        ->label(__('Reference'))
                        ->maxLength(255)
                        ->nullable(),
                ])
                ->action(function (array $data, PaymentPlanService $service): void {
                    $installment = \Modules\Billing\Models\PaymentPlanInstallment::find($data['installment_id']);
                    if (! $installment) {
                        Notification::make()->danger()->title(__('Installment not found.'))->send();
                        return;
                    }

                    $service->recordInstallmentPayment(
                        plan: $this->getRecord(),
                        installment: $installment,
                        amount: (string) $data['amount'],
                        method: \Modules\Billing\Enums\PaymentMethod::tryFrom($data['method'] ?? 'cash') ?? \Modules\Billing\Enums\PaymentMethod::Cash,
                        gateway: $data['method'] ?? 'cash',
                        reference: $data['reference'] ?? null,
                    );

                    Notification::make()
                        ->success()
                        ->title(__('Installment payment recorded'))
                        ->send();

                    $this->redirect(static::getUrl(['record' => $this->getRecord()]));
                }),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('Payment Plan'))
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('invoice.invoice_number')->label(__('Invoice')),
                        TextEntry::make('invoice.patient.display_name')->label(__('Patient')),
                        CurrencyEntry::make('total_amount')
                            ->currency(fn ($record) => $record->invoice?->currency ?? 'GHS'),
                        CurrencyEntry::make('down_payment')
                            ->currency(fn ($record) => $record->invoice?->currency ?? 'GHS'),
                        TextEntry::make('installment_count')->label(__('Installments')),
                        TextEntry::make('frequency_days')
                            ->label(__('Frequency'))
                            ->formatStateUsing(fn ($state) => __(':days days', ['days' => $state])),
                        TextEntry::make('status')->badge(),
                        TextEntry::make('start_date')->date(),
                        TextEntry::make('notes')
                            ->visible(fn ($state) => ! empty($state)),
                        CurrencyEntry::make('remainingBalance')
                            ->label(__('Remaining'))
                            ->currency(fn ($record) => $record->invoice?->currency ?? 'GHS'),
                    ]),
                Section::make(__('Installments'))
                    ->columnSpanFull()
                    ->schema(function (PaymentPlan $record) {
                        return $record->installments->sortBy('installment_number')->map(fn ($i) => TextEntry::make("installment_{$i->installment_number}")
                            ->label(__('Installment #:num', ['num' => $i->installment_number]))
                            ->formatStateUsing(function () use ($i) {
                                $status = $i->status->getLabel();
                                $due = $i->due_date->format('Y-m-d');
                                $amount = number_format((float) $i->amount, 2);
                                $paid = number_format((float) $i->paid_amount, 2);

                                return "{$amount} ({$status}) — Due: {$due}, Paid: {$paid}";
                            })
                            ->badge()
                            ->color(fn () => $i->status->getColor())
                        )->values()->toArray();
                    }),
            ]);
    }
}
