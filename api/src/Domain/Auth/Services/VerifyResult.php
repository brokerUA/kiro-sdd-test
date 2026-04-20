<?php

declare(strict_types=1);

namespace Domain\Auth\Services;

use Domain\Auth\Entities\User;

final class VerifyResult
{
    private function __construct(
        private readonly bool $success,
        private readonly ?User $user,
    ) {}

    public static function success(User $user): self
    {
        return new self(true, $user);
    }

    public static function failure(): self
    {
        return new self(false, null);
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }
}
