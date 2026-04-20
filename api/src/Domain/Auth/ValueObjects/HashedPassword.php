<?php

declare(strict_types=1);

namespace Domain\Auth\ValueObjects;

final readonly class HashedPassword
{
    public function __construct(public string $hash) {}
}
