<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Filament\Actions\RecordInvoicePaymentAction;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;
use Modules\Billing\Services\InvoiceIssuanceService;

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
            RecordInvoicePaymentAction::make()
                ->mountUsing(fn (Action $action) => $action->arguments(['invoice_id' => $record->id]))
                ->visible(fn () => ! in_array($record->status, [InvoiceStatus::Draft, InvoiceStatus::Void], true)
                    && bccomp($record->balanceDue(), '0', 2) > 0),
        ];
    }
}
