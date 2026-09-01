<?php

namespace Modules\Billing\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Modules\Billing\Models\Payment;
use Modules\Core\Http\Controllers\Api\ApiController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentStatusController extends ApiController
{
    /**
     * Payment does not extend BaseModel, so no branch global scope applies to it.
     * Without the explicit branch constraint below, any authenticated token could
     * read any branch's payment by guessing an id.
     */
    public function show(string $payment): JsonResponse
    {
        $model = $this->branchScoped(Payment::query())->find($payment);

        if (! $model) {
            throw new NotFoundHttpException;
        }

        $this->authorizeApi('view', $model);

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
