<?php

namespace Modules\Billing\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Billing\Models\Invoice;
use Modules\Billing\Services\CheckoutSessionService;
use Modules\Core\Http\Controllers\Api\ApiController;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class InvoiceCheckoutController extends ApiController
{
    public function __construct(
        protected CheckoutSessionService $checkoutSessionService
    ) {}

    public function store(Request $request, string $invoice): JsonResponse
    {
        $validated = $request->validate([
            'driver' => 'required|string|in:paystack,stripe,flutterwave,hubtel',
            'customer_email' => 'nullable|email',
            'callback_url' => 'nullable|url',
            'success_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
        ]);

        $model = Invoice::query()->withoutGlobalScopes()->find($invoice);
        if (! $model) {
            throw new NotFoundHttpException;
        }

        $intent = $this->checkoutSessionService->createForInvoice($model, $validated['driver'], [
            'customer_email' => $validated['customer_email'] ?? null,
            'callback_url' => $validated['callback_url'] ?? null,
            'success_url' => $validated['success_url'] ?? null,
            'cancel_url' => $validated['cancel_url'] ?? null,
        ]);

        return response()->json([
            'payment_intent_id' => $intent->id,
            'checkout_url' => $intent->checkout_url,
            'client_reference' => $intent->client_reference,
            'status' => $intent->status->value,
        ]);
    }
}
