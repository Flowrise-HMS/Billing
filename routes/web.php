<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\InvoicePdfController;
use Modules\Billing\Http\Controllers\LineCheckoutController;
use Modules\Billing\Http\Controllers\PaymentReceiptController;
use Modules\Billing\Http\Controllers\Public\PayInvoiceLineController;
use Modules\Billing\Http\Controllers\RevenueReportCsvController;

// Public, unauthenticated: the durable "pay this order online" link sent by
// SMS/email. Protected by a signed URL (not a session) and rate limited.
Route::middleware(['signed', 'throttle:billing-pay-line'])->group(function () {
    Route::get('/pay/lines/{line}', PayInvoiceLineController::class)
        ->name('billing.public.pay-line');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/billing/invoices/{invoice}/pdf', InvoicePdfController::class)
        ->name('billing.invoices.pdf');

    Route::get('/billing/invoices/{invoice}/line-checkout', LineCheckoutController::class)
        ->name('billing.invoices.line-checkout');

    Route::get('/billing/payments/{payment}/receipt', PaymentReceiptController::class)
        ->name('billing.payments.receipt');
    Route::get('/billing/reports/revenue.csv', RevenueReportCsvController::class)
        ->name('billing.reports.revenue.csv');
});
