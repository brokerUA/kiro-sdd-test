<?php

declare(strict_types=1);

namespace Infrastructure\Auth\RateLimiting;

final class LockStatus
{
    private function __construct(
        private readonly bool $locked,
        private readonly ?int $retryAfterSeconds,
    ) {}

    public static function unlocked(): self
    {
        return new self(false, null);
    }

    public static function locked(int $retryAfterSeconds): self
    {
        return new self(true, $retryAfterSeconds);
    }

    public function isLocked(): bool
    {
        return $this->locked;
    }

    public function getRetryAfterSeconds(): ?int
    {
        return $this->retryAfterSeconds;
    }
}
