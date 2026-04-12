<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Compta\JoinController;

Route::get('/join/{code}', JoinController::class)->name('join.landing');
