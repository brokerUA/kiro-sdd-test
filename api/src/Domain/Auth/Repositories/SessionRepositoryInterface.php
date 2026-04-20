<?php

declare(strict_types=1);

namespace Domain\Auth\Repositories;

use DateTimeImmutable;
use Domain\Auth\Entities\Session;
use Domain\Auth\ValueObjects\SessionToken;

interface SessionRepositoryInterface
{
    public function create(string $userId, SessionToken $token, DateTimeImmutable $expiresAt): Session;

    public function findByTokenHash(string $hash): ?Session;

    public function invalidate(string $tokenHash): void;

    public function invalidateAllForUser(string $userId): void;

    public function purgeExpired(DateTimeImmutable $now): void;
}
