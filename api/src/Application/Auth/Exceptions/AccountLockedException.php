<?php

declare(strict_types=1);

namespace Application\Auth\Exceptions;

final class AccountLockedException extends \RuntimeException
{
    public function __construct(private readonly int $retryAfterSeconds)
    {
        parent::__construct();
    }

    public function getRetryAfterSeconds(): int
    {
        return $this->retryAfterSeconds;
    }
}
