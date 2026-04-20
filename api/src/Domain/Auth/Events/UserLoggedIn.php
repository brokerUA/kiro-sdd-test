<?php

declare(strict_types=1);

namespace Domain\Auth\Events;

use DateTimeImmutable;

final readonly class UserLoggedIn
{
    public function __construct(
        public string $userId,
        public DateTimeImmutable $occurredAt,
    ) {}
}
