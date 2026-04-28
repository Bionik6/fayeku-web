<?php

use App\Http\Controllers\Compta\JoinController;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->group(function () {
    Route::get('/join/{code}', JoinController::class)->name('join.landing');
});
