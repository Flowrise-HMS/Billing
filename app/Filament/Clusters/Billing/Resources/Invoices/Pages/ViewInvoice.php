<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\Pages;

use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Context;
use Modules\Billing\Enums\InvoiceStatus;
use Modules\Billing\Enums\PaymentMethod;
use Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\InvoiceResource;
use Modules\Billing\Services\InvoiceAllocationBuilder;
use Modules\Billing\Services\InvoiceIssuanceService;
use Modules\Billing\Services\PaymentRecordingService;

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
            Action::make('recordCash')
                ->label(__('Record cash payment'))
                ->icon(Heroicon::OutlinedBanknotes)
                ->visible(fn () => $this->record->status !== InvoiceStatus::Draft
                    && $this->record->status !== InvoiceStatus::Void
                    && bccomp($this->record->balanceDue(), '0', 2) > 0)
                ->form([
                    TextInput::make('amount')
                        ->numeric()
                        ->required()
                        ->label(__('Amount')),
                ])
                ->action(function (array $data, PaymentRecordingService $payments, InvoiceAllocationBuilder $builder) {
                    $invoice = $this->record->fresh(['lines']);
                    $allocations = $builder->allocateAmountAcrossUnpaidLines($invoice, (string) $data['amount']);
                    if ($allocations === []) {
                        return;
                    }
                    $payments->record(
                        allocations: $allocations,
                        method: PaymentMethod::Cash,
                        gateway: 'cash',
                        currency: $invoice->currency,
                        patientId: $invoice->patient_id,
                        branchId: (string) $invoice->branch_id,
                        recordedBy: auth()->id(),
                    );
                    $this->redirect(static::getUrl(['record' => $this->record]));
                }),
        ];
    }
}
