<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\LoginController;
use Modules\Auth\Http\Controllers\LogoutController;
use Modules\Auth\Http\Controllers\OtpController;
use Modules\Auth\Http\Controllers\RegisterController;

Route::middleware('guest')->group(function () {
    Route::get('/register', [RegisterController::class, 'show'])->name('auth.register');
    Route::post('/register', [RegisterController::class, 'store'])->name('auth.register.submit');
    Route::get('/login', [LoginController::class, 'show'])->name('auth.login');
    Route::post('/login', [LoginController::class, 'store'])->name('auth.login.submit');
    Route::get('/otp', [OtpController::class, 'show'])->name('auth.otp');
    Route::post('/otp', [OtpController::class, 'verify'])->name('auth.otp.verify');
    Route::post('/otp/resend', [OtpController::class, 'resend'])->name('auth.otp.resend');
});

Route::middleware('auth')->post('/logout', [LogoutController::class, 'destroy'])->name('auth.logout');
