<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Invoices\RelationManagers;

use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use Modules\Billing\Enums\PaymentPlanStatus;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\PaymentPlanResource;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Schemas\PaymentPlanForm;
use Modules\Billing\Filament\Clusters\Billing\Resources\PaymentPlans\Tables\PaymentPlansTable;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\PaymentPlanService;

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
            ->columns(PaymentPlansTable::columns())
            ->headerActions([
                CreateAction::make('createPlan')
                    ->label(__('Create payment plan'))
                    ->icon('heroicon-o-plus')
                    ->form(PaymentPlanForm::planFields())
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
