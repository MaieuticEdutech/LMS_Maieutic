<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\ContentServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\MediaServiceProvider;

return [
    AppServiceProvider::class,
    ContentServiceProvider::class,
    FortifyServiceProvider::class,
    MediaServiceProvider::class,
];
