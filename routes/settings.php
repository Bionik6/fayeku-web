<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified.phone'])->prefix('compta')->group(function () {
    Route::livewire('settings', 'pages::settings.index')->name('settings.index');
});

Route::middleware(['auth', 'verified.phone', 'profile:sme'])->prefix('pme')->group(function () {
    Route::livewire('settings', 'pages::pme.settings.index')->name('pme.settings.index');
});
