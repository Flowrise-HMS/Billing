<?php

namespace Modules\Billing\Filament\RelationManagers;

use Filament\Actions\Action;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Billing\Filament\Actions\RecordDepositAction;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\PaymentResource;
use Modules\Billing\Models\PatientDeposit;
use Modules\Billing\Models\Payment;

class PatientPaymentsRelationManager extends RelationManager
{
    protected static string $relationship = 'payments';

    public static function getTitle(Model $ownerRecord, string $pageClass): string
    {
        return __('Payments');
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('id')
            ->columns(PaymentResource::getPaymentTableColumns())
            ->headerActions([
                RecordDepositAction::make()
                    ->mountUsing(fn (Action $action) => $action->arguments(['patient_id' => $this->getOwnerRecord()->id])),
            ])
            ->recordActions([
                ...PaymentResource::getPaymentRecordActions(),
                Action::make('viewDeposit')
                    ->label(__('View deposit'))
                    ->icon('heroicon-o-wallet')
                    ->url(function (Payment $record): ?string {
                        $deposit = PatientDeposit::query()->where('payment_id', $record->id)->first();

                        return $deposit
                            ? PaymentResource::getUrl('view', ['record' => $record])
                            : null;
                    })
                    ->visible(fn (Payment $record) => PatientDeposit::query()->where('payment_id', $record->id)->exists()),
            ])
            ->defaultSort('received_at', 'desc');
    }
}
