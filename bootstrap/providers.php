<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\AssessmentServiceProvider;
use App\Providers\ContentServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\MediaServiceProvider;
use App\Providers\PaymentServiceProvider;

return [
    AppServiceProvider::class,
    AssessmentServiceProvider::class,
    ContentServiceProvider::class,
    FortifyServiceProvider::class,
    MediaServiceProvider::class,
    PaymentServiceProvider::class,
];
