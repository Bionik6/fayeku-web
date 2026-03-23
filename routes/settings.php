<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth'])->prefix('compta')->group(function () {
    Route::redirect('settings', '/compta/settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
});

Route::middleware(['auth', 'verified.phone'])->prefix('compta')->group(function () {
    Route::livewire('settings/appearance', 'pages::settings.appearance')->name('appearance.edit');

    Route::livewire('settings/security', 'pages::settings.security')
        ->name('security.edit');
});
