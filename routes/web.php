<?php

use App\Http\Controllers\MarketingPageController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketingPageController::class, 'home'])->name('home');
Route::get('/pricing', [MarketingPageController::class, 'pricing'])->name('marketing.pricing');
Route::get('/entreprises', [MarketingPageController::class, 'enterprises'])->name('marketing.enterprises');
Route::get('/experts-comptables', [MarketingPageController::class, 'accountants'])->name('marketing.accountants');
Route::get('/experts-comptables/rejoindre', [MarketingPageController::class, 'accountantsJoin'])->name('marketing.accountants.join');
Route::get('/conformite', [MarketingPageController::class, 'compliance'])->name('marketing.compliance');
Route::get('/contact', [MarketingPageController::class, 'contact'])->name('marketing.contact');
Route::get('/mentions-legales', [MarketingPageController::class, 'legal'])->defaults('page', 'mentions-legales')->name('marketing.legal');
Route::get('/confidentialite', [MarketingPageController::class, 'legal'])->defaults('page', 'confidentialite')->name('marketing.privacy');

Route::middleware(['auth', 'verified.phone'])->group(function () {
    Route::livewire('dashboard', 'pages::dashboard.index')->name('dashboard');
    Route::livewire('clients', 'pages::clients.index')->name('clients.index');
    Route::livewire('clients/{company}', 'pages::clients.show')->name('clients.show');
});

require __DIR__.'/settings.php';
