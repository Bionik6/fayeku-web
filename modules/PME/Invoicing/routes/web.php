<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'auth', 'verified.phone', 'profile:sme'])
    ->prefix('pme')->name('pme.')
    ->group(function () {
        // TODO: Implement controllers and uncomment these routes
        // Route::get('/invoices',          InvoiceController::class)->name('invoices.index');
        // Route::get('/invoices/create',   InvoiceController::class)->name('invoices.create');
        // Route::get('/invoices/{invoice}', InvoiceController::class)->name('invoices.show');
        // Route::get('/quotes',             QuoteController::class)->name('quotes.index');
    });
