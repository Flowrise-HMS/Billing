<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\Api\BillingWebhookController;
use Modules\Billing\Http\Controllers\Api\InvoiceCheckoutController;
use Modules\Billing\Http\Controllers\Api\PaymentStatusController;

/*
| The webhook stays outside ApiRouteRegistrar and outside auth: payment providers
| call it unauthenticated, and it must keep working when the Api module is disabled.
|
| The authenticated endpoints carry `api.branch` so SetCurrentApiBranch populates the
| branch context. Without it, Payment — which has no branch global scope — was
| readable across branches by id.
*/

Route::prefix('v1')->group(function () {
    Route::post('billing/webhooks/{driver}/{branch}', [BillingWebhookController::class, 'handle'])
        ->name('billing.webhooks.handle');

    Route::middleware(['auth:sanctum', 'api.branch'])->group(function () {
        Route::post('billing/invoices/{invoice}/checkout', [InvoiceCheckoutController::class, 'store'])
            ->name('billing.invoices.checkout');
        Route::get('billing/payments/{payment}', [PaymentStatusController::class, 'show'])
            ->name('billing.payments.show');
    });
});
