<?php

use App\Enums\PME\ProposalDocumentType;
use App\Http\Controllers\Compta\ExportDownloadController;
use App\Http\Controllers\MarketingPageController;
use App\Http\Controllers\PME\CompanyLogoController;
use App\Http\Controllers\PME\InvoicePdfController;
use App\Http\Controllers\PME\ProposalDocumentPdfController;
use App\Http\Controllers\PME\TreasuryExportController;
use App\Models\PME\ProposalDocument;
use Illuminate\Support\Facades\Route;

// Bindings vers la table unifiée `proposal_documents`, scopée par type.
// Empêche un id/public_code de devis d'être servi via une URL proforma (et vice-versa).
Route::bind('quote', fn ($value) => ProposalDocument::query()
    ->where('id', $value)->ofType(ProposalDocumentType::Quote)->firstOrFail());
Route::bind('proforma', fn ($value) => ProposalDocument::query()
    ->where('id', $value)->ofType(ProposalDocumentType::Proforma)->firstOrFail());
Route::bind('quotePublic', fn ($value) => ProposalDocument::query()
    ->where('public_code', $value)->ofType(ProposalDocumentType::Quote)->firstOrFail());
Route::bind('proformaPublic', fn ($value) => ProposalDocument::query()
    ->where('public_code', $value)->ofType(ProposalDocumentType::Proforma)->firstOrFail());

Route::get('/', [MarketingPageController::class, 'home'])->name('home');
Route::get('/pricing', [MarketingPageController::class, 'pricing'])->name('marketing.pricing');
Route::get('/entreprises', [MarketingPageController::class, 'enterprises'])->name('marketing.enterprises');
Route::get('/accountants', [MarketingPageController::class, 'accountants'])->name('marketing.accountants');
Route::get('/accountant/join', [MarketingPageController::class, 'accountantsJoin'])->name('marketing.accountants.join');
Route::post('/accountant/join', [MarketingPageController::class, 'accountantsJoinStore'])->name('marketing.accountants.join.store');
Route::get('/conformite', [MarketingPageController::class, 'compliance'])->name('marketing.compliance');
Route::get('/contact', [MarketingPageController::class, 'contact'])->name('marketing.contact');
Route::get('/mentions-legales', [MarketingPageController::class, 'legal'])->defaults('page', 'mentions-legales')->name('marketing.legal');
Route::get('/confidentialite', [MarketingPageController::class, 'legal'])->defaults('page', 'confidentialite')->name('marketing.privacy');

Route::middleware(['auth', 'verified.phone', 'profile:accountant_firm'])->prefix('compta')->group(function () {
    Route::redirect('/', '/compta/dashboard');
    Route::livewire('dashboard', 'pages::compta.dashboard.index')->name('dashboard');
    Route::livewire('alertes', 'pages::compta.alerts.index')->name('alerts.index');
    Route::livewire('clients', 'pages::compta.clients.index')->name('clients.index');
    Route::livewire('clients/{company}', 'pages::compta.clients.show')->name('clients.show');
    Route::livewire('exports', 'pages::compta.export.index')->name('export.index');
    Route::get('exports/{exportHistory}/download', ExportDownloadController::class)->name('export.download');
    Route::livewire('commissions', 'pages::compta.commissions.index')->name('commissions.index');
    Route::livewire('invitations', 'pages::compta.invitations.index')->name('invitations.index');
    Route::livewire('support', 'pages::compta.support.index')->name('support.index');
});

// PDF publics — pas d'auth, URLs courtes pour SMS/WhatsApp.
// La syntaxe `{param:public_code}` indique à Laravel d'utiliser `public_code`
// comme clé de route (binding + génération d'URL). Notre `Route::bind()`
// au-dessus prend le relais sur la résolution pour ajouter le scope par type.
Route::get('f/{invoice:public_code}/pdf', InvoicePdfController::class)->name('pme.invoices.pdf');
Route::get('d/{quotePublic:public_code}/pdf', ProposalDocumentPdfController::class)->name('pme.quotes.pdf');
Route::get('p/{proformaPublic:public_code}/pdf', ProposalDocumentPdfController::class)->name('pme.proformas.pdf');

Route::middleware(['auth', 'verified.phone', 'profile:sme'])->prefix('pme')->group(function () {
    Route::redirect('/', '/pme/dashboard');
    Route::livewire('dashboard', 'pages::pme.dashboard.index')->name('pme.dashboard');
    Route::livewire('invoices/create', 'pages::pme.invoices.form')->name('pme.invoices.create');
    Route::livewire('invoices/{invoice}/edit', 'pages::pme.invoices.form')->name('pme.invoices.edit');
    Route::livewire('invoices', 'pages::pme.invoices.index')->name('pme.invoices.index');
    Route::livewire('invoices/{invoice}', 'pages::pme.invoices.show')->name('pme.invoices.show');
    Route::livewire('quotes/create', 'pages::pme.quotes.form')->name('pme.quotes.create');
    Route::livewire('quotes/{quote}/edit', 'pages::pme.quotes.form')->name('pme.quotes.edit');
    Route::livewire('quotes/{quote}', 'pages::pme.quotes.show')->name('pme.quotes.show');
    Route::livewire('quotes', 'pages::pme.quotes.index')->name('pme.quotes.index');
    Route::livewire('proformas/create', 'pages::pme.proformas.form')->name('pme.proformas.create');
    Route::livewire('proformas/{proforma}/edit', 'pages::pme.proformas.form')->name('pme.proformas.edit');
    Route::livewire('proformas/{proforma}', 'pages::pme.proformas.show')->name('pme.proformas.show');
    // Liste unifiée : /pme/proformas redirige vers la page Devis & Proformas
    Route::redirect('proformas', '/pme/quotes')->name('pme.proformas.index');
    Route::livewire('clients', 'pages::pme.clients.index')->name('pme.clients.index');
    Route::livewire('clients/{client}', 'pages::pme.clients.show')->name('pme.clients.show');
    Route::livewire('collections', 'pages::pme.collection.index')->name('pme.collection.index');
    Route::livewire('treasury', 'pages::pme.treasury.index')->name('pme.treasury.index');
    Route::get('treasury/export', TreasuryExportController::class)->name('pme.treasury.export');
    Route::get('company/logo', CompanyLogoController::class)->name('pme.company.logo');
    Route::livewire('support', 'pages::pme.support.index')->name('pme.support.index');
});

require __DIR__.'/settings.php';
