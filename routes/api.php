<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\Api\BillingWebhookController;
use Modules\Billing\Http\Controllers\Api\InvoiceCheckoutController;
use Modules\Billing\Http\Controllers\Api\PaymentStatusController;

Route::prefix('v1')->group(function () {
    Route::post('billing/webhooks/{driver}/{branch}', [BillingWebhookController::class, 'handle'])
        ->name('billing.webhooks.handle');

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('billing/invoices/{invoice}/checkout', [InvoiceCheckoutController::class, 'store'])
            ->name('billing.invoices.checkout');
        Route::get('billing/payments/{payment}', [PaymentStatusController::class, 'show'])
            ->name('billing.payments.show');
    });
});
