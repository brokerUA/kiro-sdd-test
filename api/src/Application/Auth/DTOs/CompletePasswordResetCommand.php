<?php

declare(strict_types=1);

namespace Application\Auth\DTOs;

final readonly class CompletePasswordResetCommand
{
    public function __construct(
        public string $tokenRaw,
        public string $newPassword,
    ) {}
}
