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
        return [
            Action::make('editDraft')
                ->label(__('Edit lines'))
                ->icon(Heroicon::OutlinedPencilSquare)
                ->visible(fn () => $this->record->status === InvoiceStatus::Draft)
                ->url(fn () => InvoiceResource::getUrl('edit', ['record' => $this->record])),
            Action::make('issue')
                ->label(__('Issue invoice'))
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->visible(fn () => $this->record->status === InvoiceStatus::Draft)
                ->requiresConfirmation()
                ->action(function (InvoiceIssuanceService $issuance) {
                    Context::add('current_branch_id', $this->record->branch_id);
                    try {
                        $issuance->issue($this->record->fresh());
                    } finally {
                        Context::forget('current_branch_id');
                    }
                    $this->redirect(static::getUrl(['record' => $this->record]));
                }),
            RecordInvoicePaymentAction::make()
                ->mountUsing(fn (Action $action) => $action->arguments(['invoice_id' => $this->record->id]))
                ->visible(fn () => ! in_array($this->record->status, [InvoiceStatus::Draft, InvoiceStatus::Void], true)
                    && bccomp($this->record->balanceDue(), '0', 2) > 0),
        ];
    }
}
