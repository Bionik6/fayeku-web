<?php

use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\Sme\RegisterController;
use Illuminate\Support\Facades\Route;

Route::prefix('api/auth')->group(function () {
    Route::post('/register', [RegisterController::class, 'store'])->name('api.auth.register');
    Route::post('/login', [LoginController::class, 'store'])->name('api.auth.login');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('api.auth.forgot-password');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('api.auth.reset-password');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/verify-email', [EmailVerificationController::class, 'verify'])->name('api.auth.verify-email');
        Route::post('/verify-email/resend', [EmailVerificationController::class, 'resend'])->name('api.auth.verify-email.resend');
        Route::post('/logout', [LogoutController::class, 'destroy'])->name('api.auth.logout');
    });
});
