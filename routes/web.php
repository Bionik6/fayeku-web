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

Route::middleware(['auth', 'verified.phone', 'profile:accountant_firm'])->prefix('compta')->group(function () {
    Route::livewire('dashboard', 'pages::dashboard.index')->name('dashboard');
    Route::livewire('alertes', 'pages::alerts.index')->name('alerts.index');
    Route::livewire('clients', 'pages::clients.index')->name('clients.index');
    Route::livewire('clients/{company}', 'pages::clients.show')->name('clients.show');
    Route::livewire('exports', 'pages::export.index')->name('export.index');
    Route::livewire('commissions', 'pages::commissions.index')->name('commissions.index');
    Route::livewire('invitations', 'pages::invitations.index')->name('invitations.index');
    Route::livewire('support', 'pages::support.index')->name('support.index');
});

Route::middleware(['auth', 'verified.phone', 'profile:sme'])->prefix('pme')->group(function () {
    Route::livewire('dashboard', 'pages::pme.dashboard.index')->name('pme.dashboard');
    Route::livewire('invoices', 'pages::pme.invoices.index')->name('pme.invoices.index');
    Route::livewire('clients', 'pages::pme.clients.index')->name('pme.clients.index');
    Route::livewire('collections', 'pages::pme.collection.index')->name('pme.collection.index');
    Route::livewire('treasury', 'pages::pme.treasury.index')->name('pme.treasury.index');
    Route::livewire('support', 'pages::pme.support.index')->name('pme.support.index');
});

require __DIR__.'/settings.php';
