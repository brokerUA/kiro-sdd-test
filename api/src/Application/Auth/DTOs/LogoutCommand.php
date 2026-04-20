<?php

declare(strict_types=1);

namespace Application\Auth\DTOs;

final readonly class LogoutCommand
{
    public function __construct(
        public string $tokenHash,
    ) {}
}
