<?php

use Illuminate\Support\Facades\Route;
use Modules\Auth\Http\Controllers\LoginController;
use Modules\Auth\Http\Controllers\LogoutController;
use Modules\Auth\Http\Controllers\OtpController;
use Modules\Auth\Http\Controllers\RegisterController;

Route::prefix('api/auth')->group(function () {
    Route::post('/register', [RegisterController::class, 'store']);
    Route::post('/login', [LoginController::class, 'store']);
    Route::post('/otp/verify', [OtpController::class, 'verify']);
    Route::post('/otp/resend', [OtpController::class, 'resend']);
    Route::middleware('auth:sanctum')->post('/logout', [LogoutController::class, 'destroy']);
});
