<?php

namespace App\Parsers;

use Illuminate\Contracts\Container\Container;

abstract class AbstractParser
{
    public function __construct(
        protected Container $container,
    ) {}

    protected function make(string $abstract): mixed
    {
        return $this->container->make($abstract);
    }
}
