<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Enums\PaymentPlanStatus;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\PaymentPlanResource;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Schemas\PaymentPlanForm;
use Modules\Billing\Models\PaymentPlanInstallment;
use Modules\Billing\Services\PaymentPlanService;

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
                ->form(fn () => PaymentPlanForm::collectInstallmentFields($this->getRecord()))
                ->action(function (array $data, PaymentPlanService $service): void {
                    $installment = PaymentPlanInstallment::find($data['installment_id']);
                    if (! $installment) {
                        Notification::make()->danger()->title(__('Installment not found.'))->send();

                        return;
                    }

                    $service->recordInstallmentPayment(
                        plan: $this->getRecord(),
                        installment: $installment,
                        amount: (string) $data['amount'],
                        method: PaymentMethod::tryFrom($data['method'] ?? 'cash') ?? PaymentMethod::Cash,
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
}
