<?php

namespace Modules\Billing\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Billing\Models\Payment;
use Modules\Core\Http\Controllers\Api\ApiController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentStatusController extends ApiController
{
    public function show(string $payment): JsonResponse
    {
        $model = Payment::query()->find($payment);
        if (! $model) {
            throw new NotFoundHttpException;
        }

        return response()->json([
            'id' => $model->id,
            'amount' => $model->amount,
            'currency' => $model->currency,
            'method' => $model->method->value,
            'gateway' => $model->gateway,
            'provider_transaction_id' => $model->provider_transaction_id,
            'received_at' => $model->received_at?->toIso8601String(),
        ]);
    }
}
