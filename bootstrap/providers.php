<?php

use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use Modules\Auth\Providers\AuthModuleServiceProvider;
use Modules\Compta\Providers\ComptaModuleServiceProvider;
use Modules\PME\Providers\PmeModuleServiceProvider;
use Modules\Shared\Providers\SharedServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    SharedServiceProvider::class,
    AuthModuleServiceProvider::class,
    PmeModuleServiceProvider::class,
    ComptaModuleServiceProvider::class,
];
