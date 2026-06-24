<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Pages;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\PaymentPlanResource;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\PaymentPlanService;

class CreatePaymentPlan extends CreateRecord
{
    protected static string $resource = PaymentPlanResource::class;

    protected function getFormSchema(): array
    {
        return [
            Select::make('invoice_id')
                ->label(__('Invoice'))
                ->relationship('invoice', 'invoice_number')
                ->searchable()
                ->preload()
                ->required()
                ->live()
                ->afterStateUpdated(function ($state, callable $set) {
                    if ($state) {
                        $invoice = Invoice::find($state);
                        if ($invoice) {
                            $set('total_amount', (string) $invoice->balanceDue());
                        }
                    }
                }),
            TextInput::make('total_amount')
                ->label(__('Invoice balance'))
                ->disabled()
                ->dehydrated(false)
                ->numeric(),
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
        ];
    }

    protected function handleRecordCreation(array $data): Model
    {
        $invoice = Invoice::with('paymentPlan')->findOrFail($data['invoice_id']);

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

        return $plan;
    }
}
