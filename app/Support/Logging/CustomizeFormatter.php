<?php

namespace App\Support\Logging;

use Monolog\Formatter\JsonFormatter;
use Monolog\Logger;

class CustomizeFormatter
{
    public function __invoke(Logger $logger): void
    {
        foreach ($logger->getHandlers() as $handler) {
            $handler->setFormatter(new JsonFormatter);
        }
    }
}
