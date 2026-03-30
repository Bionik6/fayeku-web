<?php

use Illuminate\Support\Facades\Route;
use Modules\Compta\Partnership\Http\Controllers\JoinController;

Route::get('/join/{code}', JoinController::class)->name('join.landing');
