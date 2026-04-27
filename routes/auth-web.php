<?php

use App\Http\Controllers\Auth\Accountant\LoginController as AccountantLoginController;
use App\Http\Controllers\Auth\Accountant\PasswordResetController as AccountantPasswordResetController;
use App\Http\Controllers\Auth\AccountantActivationController;
use App\Http\Controllers\Auth\CompanySetupController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\Sme\LoginController as SmeLoginController;
use App\Http\Controllers\Auth\Sme\PasswordResetController as SmePasswordResetController;
use App\Http\Controllers\Auth\Sme\RegisterController as SmeRegisterController;
use Illuminate\Support\Facades\Route;

Route::middleware(['web', 'guest'])->group(function () {
    // Portail PME (téléphone + OTP)
    Route::prefix('sme')->name('sme.auth.')->group(function () {
        Route::get('/login', [SmeLoginController::class, 'show'])->name('login');
        Route::post('/login', [SmeLoginController::class, 'store'])->name('login.submit');
        Route::get('/register', [SmeRegisterController::class, 'show'])->name('register');
        Route::post('/register', [SmeRegisterController::class, 'store'])->name('register.submit');
        Route::get('/forgot-password', [SmePasswordResetController::class, 'showForgotForm'])->name('forgot-password');
        Route::post('/forgot-password', [SmePasswordResetController::class, 'sendResetOtp'])->name('forgot-password.submit');
        Route::get('/reset-password', [SmePasswordResetController::class, 'showResetForm'])->name('reset-password');
        Route::post('/reset-password', [SmePasswordResetController::class, 'reset'])->name('reset-password.submit');
    });

    // Portail Expert-Comptable (email + lien)
    Route::prefix('accountant')->name('accountant.auth.')->group(function () {
        Route::get('/login', [AccountantLoginController::class, 'show'])->name('login');
        Route::post('/login', [AccountantLoginController::class, 'store'])->name('login.submit');
        Route::get('/forgot-password', [AccountantPasswordResetController::class, 'showForgotForm'])->name('forgot-password');
        Route::post('/forgot-password', [AccountantPasswordResetController::class, 'sendResetLink'])->name('forgot-password.submit');
        Route::get('/reset-password/{token}', [AccountantPasswordResetController::class, 'showResetForm'])->name('reset-password');
        Route::post('/reset-password', [AccountantPasswordResetController::class, 'reset'])->name('reset-password.submit');
    });

    // Activation (lien magique reçu par email après qualification d'un lead)
    Route::get('/accountant/activation/{token}', [AccountantActivationController::class, 'show'])
        ->name('accountant.activation');
    Route::post('/accountant/activation/{token}', [AccountantActivationController::class, 'process'])
        ->name('accountant.activation.process');

});

// /login conserve le nom de route `login` requis par Laravel (auth middleware
// y redirige les guests). Toujours un redirect 302 vers le portail PME — par
// défaut, le visiteur sans profil tombe sur l'espace PME.
Route::middleware('web')->group(function () {
    Route::redirect('/login', '/sme/login')->name('login');
    Route::redirect('/register', '/sme/register');
    Route::redirect('/forgot-password', '/sme/forgot-password');
    Route::redirect('/reset-password', '/sme/reset-password');
    Route::redirect('/otp', '/sme/otp');
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
