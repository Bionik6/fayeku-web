<?php

use App\Http\Controllers\Auth\AccountantActivationController;
use App\Http\Controllers\Auth\CompanySetupController;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\MagicLinkController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\Sme\RegisterController as SmeRegisterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'guest'])->group(function () {
    // Connexion unifiée — identifiant (email ou téléphone) + mot de passe.
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.submit');

    // Mot de passe oublié — email pour PME et comptable.
    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');

    // Inscription PME (la seule inscription self-serve — les cabinets sont activés via /accountant/join)
    Route::get('/register', [SmeRegisterController::class, 'show'])->name('register');
    Route::post('/register', [SmeRegisterController::class, 'store'])->name('register.submit');

    // Reset password unifié (lien signé par email, sert PME et comptable).
    Route::get('/auth/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])
        ->name('auth.reset-password');
    Route::post('/auth/reset-password', [PasswordResetController::class, 'reset'])
        ->name('auth.reset-password.submit');

    // Magic link — connexion sans mot de passe via lien signé envoyé par email.
    Route::get('/auth/magic-link', [MagicLinkController::class, 'request'])
        ->name('auth.magic-link.request');
    Route::post('/auth/magic-link', [MagicLinkController::class, 'send'])
        ->middleware('throttle:magic-link')
        ->name('auth.magic-link.send');
    // Pas de middleware `signed` ici : le controller vérifie hasValidSignature()
    // lui-même pour rendre une réponse user-friendly (redirect vers /login plutôt
    // qu'un 403 brut) en cas de lien tampered ou expiré.
    Route::get('/auth/magic-link/consume/{user}', [MagicLinkController::class, 'consume'])
        ->name('auth.magic-link.consume');

    // Activation comptable (lien magique reçu par email après qualification d'un lead).
    Route::get('/accountant/activation/{token}', [AccountantActivationController::class, 'show'])
        ->name('accountant.activation');
    Route::post('/accountant/activation/{token}', [AccountantActivationController::class, 'process'])
        ->name('accountant.activation.process');
});

Route::middleware(['web', 'auth'])->group(function () {
    // Vérification email post-inscription (code à 6 chiffres reçu par email).
    Route::get('/auth/verify-email', [EmailVerificationController::class, 'show'])->name('auth.verify-email');
    Route::post('/auth/verify-email', [EmailVerificationController::class, 'verify'])->name('auth.verify-email.verify');
    Route::post('/auth/verify-email/resend', [EmailVerificationController::class, 'resend'])->name('auth.verify-email.resend');

    Route::post('/logout', [LogoutController::class, 'destroy'])->name('auth.logout');
});

Route::middleware(['web', 'auth', 'verified.email'])->group(function () {
    Route::get('/company-setup', [CompanySetupController::class, 'show'])->name('auth.company-setup');
    Route::post('/company-setup', [CompanySetupController::class, 'store'])->name('auth.company-setup.submit');
});
