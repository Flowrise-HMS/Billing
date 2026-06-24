<?php

namespace Modules\Billing\Filament\Clusters\Billing\Resources\Payments\Pages;

use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Modules\Billing\Filament\Actions\RefundPaymentAction;
use Modules\Billing\Filament\Clusters\Billing\Resources\Payments\PaymentResource;

class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RefundPaymentAction::make()
                ->mountUsing(fn (Action $action) => $action->arguments(['payment_id' => $this->getRecord()->id])),
        ];
    }
}
