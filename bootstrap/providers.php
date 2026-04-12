<?php

use App\Providers\AppServiceProvider;
use App\Providers\Auth\AuthModuleServiceProvider;
use App\Providers\Compta\ComptaModuleServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\PME\PmeModuleServiceProvider;
use App\Providers\Shared\SharedServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    SharedServiceProvider::class,
    AuthModuleServiceProvider::class,
    PmeModuleServiceProvider::class,
    ComptaModuleServiceProvider::class,
];
