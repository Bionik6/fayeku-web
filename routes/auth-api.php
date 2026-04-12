<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;

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
