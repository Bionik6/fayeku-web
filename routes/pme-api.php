<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['api', 'auth:sanctum', 'verified.email'])
    ->prefix('api/pme')
    ->group(function () {
        // TODO: Implement controllers and uncomment these routes
        // Route::apiResource('invoices', \App\Http\Controllers\PME\InvoiceController::class);
        // Route::apiResource('quotes',   \App\Http\Controllers\PME\QuoteController::class);
    });
