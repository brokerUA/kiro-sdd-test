<?php

declare(strict_types=1);

namespace Domain\Auth\Repositories;

use Domain\Auth\Entities\User;
use Domain\Auth\ValueObjects\Email;

interface UserRepositoryInterface
{
    public function findById(string $id): ?User;

    public function findByEmail(Email $email): ?User;

    public function save(User $user): void;
}
