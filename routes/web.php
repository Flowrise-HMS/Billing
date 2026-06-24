<?php

use Illuminate\Support\Facades\Route;
use Modules\Billing\Http\Controllers\InvoicePdfController;
use Modules\Billing\Http\Controllers\LineCheckoutController;
use Modules\Billing\Http\Controllers\PaymentReceiptController;
use Modules\Billing\Http\Controllers\RevenueReportCsvController;

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
