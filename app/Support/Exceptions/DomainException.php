<?php

namespace App\Support\Exceptions;

use Exception;

class DomainException extends Exception
{
    public function __construct(
        string $message,
        protected int $status = 422,
        protected array $context = [],
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $status, $previous);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function context(): array
    {
        return $this->context;
    }
}
