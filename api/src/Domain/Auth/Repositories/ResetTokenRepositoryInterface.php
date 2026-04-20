<?php

declare(strict_types=1);

namespace Domain\Auth\Repositories;

use DateTimeImmutable;
use Domain\Auth\Entities\PasswordResetToken;
use Domain\Auth\ValueObjects\ResetToken;

interface ResetTokenRepositoryInterface
{
    public function create(string $userId, ResetToken $token, DateTimeImmutable $expiresAt): PasswordResetToken;

    public function findByTokenHash(string $hash): ?PasswordResetToken;

    public function invalidateForUser(string $userId): void;

    public function save(PasswordResetToken $token): void;
}
