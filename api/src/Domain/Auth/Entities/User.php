<?php

declare(strict_types=1);

namespace Domain\Auth\Entities;

use DateTimeImmutable;
use Domain\Auth\ValueObjects\Email;
use Domain\Auth\ValueObjects\HashedPassword;

class User
{
    public function __construct(
        public readonly string $id,
        public Email $email,
        public HashedPassword $passwordHash,
        public DateTimeImmutable $createdAt,
        public DateTimeImmutable $updatedAt,
    ) {}

    public function changePassword(HashedPassword $newHash): void
    {
        $this->passwordHash = $newHash;
        $this->updatedAt = new DateTimeImmutable();
    }
}
