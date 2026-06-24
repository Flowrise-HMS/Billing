<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages;

use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentPlanStatus;
use Modules\Billing\Filament\Actions\ApplyDepositAction;
use Modules\Billing\Filament\Actions\RecordInvoicePaymentAction;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Billing\Services\PaymentPlanService;

class ViewInvoice extends ViewRecord
{
    protected static string $resource = InvoiceResource::class;

    protected function getHeaderActions(): array
    {
        $record = $this->getRecord();

        return [
            Action::make('editDraft')
                ->label(__('Edit lines'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->visible(fn () => $record?->status === InvoiceStatus::Draft)
                ->url(fn () => InvoiceResource::getUrl('edit', ['record' => $record])),
            Action::make('issue')
                ->label(__('Issue invoice'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn () => $record?->status === InvoiceStatus::Draft)
                ->requiresConfirmation()
                ->action(function (InvoiceIssuanceService $issuance) use ($record) {
                    Context::add('current_branch_id', $record?->branch_id);
                    try {
                        $issuance->issue($record?->fresh());
                    } finally {
                        Context::forget('current_branch_id');
                    }
                    $this->redirect(static::getUrl(['record' => $record]));
                }),
            Action::make('activities')
                ->label('Activities')
                ->icon('heroicon-o-bell-alert')
                ->url(fn () => InvoiceResource::getUrl('activities', ['record' => $record])),
            Action::make('void')
                ->label(__('Void invoice'))
                ->icon(Heroicon::OutlinedNoSymbol)
                ->color('danger')
                ->visible(fn () => Auth::user()?->can('void', $record))
                ->requiresConfirmation()
                ->modalHeading(__('Void invoice'))
                ->modalDescription(__('Are you sure you want to void this invoice? This action cannot be undone.'))
                ->action(function (PaymentPlanService $planService) use ($record) {
                    $record->update(['status' => InvoiceStatus::Void]);

                    $activePlan = $record->paymentPlan()
                        ->where('status', PaymentPlanStatus::Active)
                        ->first();
                    if ($activePlan) {
                        $planService->cancelPlan($activePlan);
                    }

                    Notification::make()
                        ->success()
                        ->title(__('Invoice voided'))
                        ->send();
                    $this->redirect(static::getUrl(['record' => $record]));
                }),
            RecordInvoicePaymentAction::make()
                ->mountUsing(fn (Action $action) => $action->arguments(['invoice_id' => $record->id]))
                ->visible(fn () => ! in_array($record->status, [InvoiceStatus::Draft, InvoiceStatus::Void], true)
                    && bccomp($record->balanceDue(), '0', 2) > 0),
            ApplyDepositAction::make()
                ->mountUsing(fn (Action $action) => $action->arguments(['invoice_id' => $record->id])),
        ];
    }
}
