<?php

use App\Http\Controllers\Auth\LogoutController;
use App\Http\Controllers\Auth\OtpController;
use App\Http\Controllers\Auth\Sme\LoginController;
use App\Http\Controllers\Auth\Sme\PasswordResetController;
use App\Http\Controllers\Auth\Sme\RegisterController;
use Illuminate\Support\Facades\Route;

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
