<?php

declare(strict_types=1);

namespace Domain\Auth\ValueObjects;

final readonly class ResetToken
{
    /**
     * @param string $raw  64-char hex string (256-bit random token)
     * @param string $hash SHA-256 hash of $raw for database storage
     */
    public function __construct(
        public string $raw,
        public string $hash,
    ) {}
}
