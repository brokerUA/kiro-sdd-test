<?php

declare(strict_types=1);

namespace Domain\Auth\Services;

final class PolicyResult
{
    private function __construct(
        private readonly bool $valid,
        private readonly array $violations,
    ) {}

    public static function valid(): self
    {
        return new self(true, []);
    }

    public static function invalid(array $violations): self
    {
        return new self(false, $violations);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function getViolations(): array
    {
        return $this->violations;
    }
}
