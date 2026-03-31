<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\CompanySetupController;
use Modules\Auth\Http\Controllers\LoginController;
use Modules\Auth\Http\Controllers\LogoutController;
use Modules\Auth\Http\Controllers\OtpController;
use Modules\Auth\Http\Controllers\PasswordResetController;
use Modules\Auth\Http\Controllers\RegisterController;

Route::middleware(['web', 'guest'])->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('auth.register');
    Route::post('/register', [RegisterController::class, 'store'])->name('auth.register.submit');
    Route::get('/login', [LoginController::class, 'show'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->name('auth.login.submit');
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('auth.forgot-password');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetOtp'])->name('auth.forgot-password.submit');
    Route::get('/reset-password', [PasswordResetController::class, 'showResetForm'])->name('auth.reset-password');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('auth.reset-password.submit');
});

Route::middleware(['web', 'auth'])->group(function () {
    Route::get('/otp', [OtpController::class, 'show'])->name('auth.otp');
    Route::post('/otp', [OtpController::class, 'verify'])->name('auth.otp.verify');
    Route::post('/otp/resend', [OtpController::class, 'resend'])->name('auth.otp.resend');
    Route::post('/logout', [LogoutController::class, 'destroy'])->name('auth.logout');
});

Route::middleware(['web', 'auth', 'verified.phone'])->group(function () {
    Route::get('/company-setup', [CompanySetupController::class, 'show'])->name('auth.company-setup');
    Route::post('/company-setup', [CompanySetupController::class, 'store'])->name('auth.company-setup.submit');
});
