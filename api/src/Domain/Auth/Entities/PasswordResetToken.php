<?php

declare(strict_types=1);

namespace Domain\Auth\Entities;

use DateTimeImmutable;

class PasswordResetToken
{
    public function __construct(
        public readonly string $id,
        public readonly string $userId,
        public readonly string $tokenHash,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $expiresAt,
        public ?DateTimeImmutable $usedAt,
    ) {}

    public function isExpired(DateTimeImmutable $now): bool
    {
        return $now >= $this->expiresAt;
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }

    public function markUsed(DateTimeImmutable $now): void
    {
        $this->usedAt = $now;
    }
}
