<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\LoginController;
use Modules\Auth\Http\Controllers\LogoutController;
use Modules\Auth\Http\Controllers\OtpController;
use Modules\Auth\Http\Controllers\PasswordResetController;
use Modules\Auth\Http\Controllers\RegisterController;

Route::prefix('api/auth')->group(function () {
    Route::post('/register', [RegisterController::class, 'store'])->name('api.auth.register');
    Route::post('/login', [LoginController::class, 'store'])->name('api.auth.login');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetOtp'])->name('api.auth.forgot-password');
    Route::post('/reset-password', [PasswordResetController::class, 'reset'])->name('api.auth.reset-password');

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/otp/verify', [OtpController::class, 'verify'])->name('api.auth.otp.verify');
        Route::post('/otp/resend', [OtpController::class, 'resend'])->name('api.auth.otp.resend');
        Route::post('/logout', [LogoutController::class, 'destroy'])->name('api.auth.logout');
    });
});
