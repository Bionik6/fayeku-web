<?php

use App\Http\Controllers\Auth\Accountant\PasswordResetController as AccountantPasswordResetController;
use App\Http\Controllers\Auth\AccountantActivationController;
use App\Http\Controllers\Auth\CompanySetupController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\Sme\PasswordResetController as SmePasswordResetController;
use App\Http\Controllers\Auth\Sme\RegisterController as SmeRegisterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'guest'])->group(function () {
    // Connexion unifiée (toggle PME / Cabinet)
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('login.submit');

    // Mot de passe oublié unifié (toggle PME / Cabinet)
    Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.email');

    // Inscription PME (la seule inscription self-serve — les cabinets sont activés via /accountant/join)
    Route::get('/register', [SmeRegisterController::class, 'show'])->name('register');
    Route::post('/register', [SmeRegisterController::class, 'store'])->name('register.submit');

    // Suite du reset PME (OTP) — pas de toggle, le profil est figé après l'envoi du code
    Route::prefix('sme')->name('sme.auth.')->group(function () {
        Route::get('/reset-password', [SmePasswordResetController::class, 'showResetForm'])->name('reset-password');
        Route::post('/reset-password', [SmePasswordResetController::class, 'reset'])->name('reset-password.submit');
    });

    // Suite du reset Cabinet (lien e-mail) — `accountant.auth.reset-password` reste
    // référencé par User::sendPasswordResetNotification, ne pas renommer.
    Route::prefix('accountant')->name('accountant.auth.')->group(function () {
        Route::get('/reset-password/{token}', [AccountantPasswordResetController::class, 'showResetForm'])->name('reset-password');
        Route::post('/reset-password', [AccountantPasswordResetController::class, 'reset'])->name('reset-password.submit');
    });

    // Activation (lien magique reçu par email après qualification d'un lead)
    Route::get('/accountant/activation/{token}', [AccountantActivationController::class, 'show'])
        ->name('accountant.activation');
    Route::post('/accountant/activation/{token}', [AccountantActivationController::class, 'process'])
        ->name('accountant.activation.process');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::prefix('sme')->name('sme.auth.')->group(function () {
        Route::get('/otp', [OtpController::class, 'show'])->name('otp');
        Route::post('/otp', [OtpController::class, 'verify'])->name('otp.verify');
        Route::post('/otp/resend', [OtpController::class, 'resend'])->name('otp.resend');
    });

    Route::post('/logout', [LogoutController::class, 'destroy'])->name('auth.logout');
});

Route::middleware(['web', 'auth', 'verified.phone'])->group(function () {
    Route::get('/company-setup', [CompanySetupController::class, 'show'])->name('auth.company-setup');
    Route::post('/company-setup', [CompanySetupController::class, 'store'])->name('auth.company-setup.submit');
});
