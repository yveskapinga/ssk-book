<?php

namespace App\Shared;

final class DomainException extends \RuntimeException
{
    public function __construct(string $message, public readonly int $statusCode = 422)
    {
        parent::__construct($message);
    }
}
