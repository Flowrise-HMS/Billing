<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Pages;

use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\PaymentPlanResource;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\PaymentPlanService;

class CreatePaymentPlan extends CreateRecord
{
    protected static string $resource = PaymentPlanResource::class;

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
