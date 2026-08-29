<?php

use App\Providers\AppServiceProvider;
use App\Providers\AuthServiceProvider;
use App\Providers\DomainServiceProvider;
use App\Providers\EventServiceProvider;
use App\Providers\ParserServiceProvider;
use App\Providers\RepositoryServiceProvider;
use App\Providers\TransferServiceProvider;

return [
    AppServiceProvider::class,
    AuthServiceProvider::class,
    EventServiceProvider::class,
    RepositoryServiceProvider::class,
    DomainServiceProvider::class,
    TransferServiceProvider::class,
    ParserServiceProvider::class,
];
