<?php

declare(strict_types=1);

namespace Domain\Auth\Entities;

use DateTimeImmutable;

class Session
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $tokenHash,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $lastActivityAt,
        public DateTimeImmutable $expiresAt,
    ) {}

    public function isExpired(DateTimeImmutable $now, int $inactivityTimeoutSeconds): bool
    {
        if ($now >= $this->expiresAt) {
            return true;
        }

        $inactivityDeadline = $this->lastActivityAt->modify("+{$inactivityTimeoutSeconds} seconds");

        return $now >= $inactivityDeadline;
    }

    public function touch(DateTimeImmutable $now): void
    {
        $this->lastActivityAt = $now;
    }
}
