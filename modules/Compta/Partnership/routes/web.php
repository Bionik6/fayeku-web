<?php

use Illuminate\Support\Facades\Route;
use Modules\Compta\Partnership\Http\Controllers\InvitationLandingController;

Route::get('/invite/{token}', InvitationLandingController::class)->name('invitation.landing');
