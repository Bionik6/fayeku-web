<?php

use App\Providers\AppServiceProvider;
use App\Providers\Auth\AuthModuleServiceProvider;
use App\Providers\Compta\ComptaModuleServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\PME\PmeModuleServiceProvider;
use App\Providers\Shared\SharedServiceProvider;

return [
    AppServiceProvider::class,
    AuthModuleServiceProvider::class,
    ComptaModuleServiceProvider::class,
    FortifyServiceProvider::class,
    PmeModuleServiceProvider::class,
    SharedServiceProvider::class,
];
