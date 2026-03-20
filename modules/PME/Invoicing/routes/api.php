<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum', 'verified.phone'])
    ->prefix('api/pme')
    ->group(function () {
        Route::apiResource('invoices', \Modules\PME\Invoicing\Http\Controllers\InvoiceController::class);
        Route::apiResource('quotes',   \Modules\PME\Invoicing\Http\Controllers\QuoteController::class);
    });
