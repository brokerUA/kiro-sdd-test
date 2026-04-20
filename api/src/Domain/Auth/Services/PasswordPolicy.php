<?php

declare(strict_types=1);

namespace Domain\Auth\Services;

use Domain\Auth\ValueObjects\HashedPassword;

final class PasswordPolicy
{
    public function validate(string $password): PolicyResult
    {
        $violations = [];

        if (strlen($password) < 8) {
            $violations[] = 'Password must be at least 8 characters';
        }

        if (!preg_match('/[A-Z]/', $password)) {
            $violations[] = 'Password must contain at least one uppercase letter';
        }

        if (!preg_match('/[a-z]/', $password)) {
            $violations[] = 'Password must contain at least one lowercase letter';
        }

        if (!preg_match('/[0-9]/', $password)) {
            $violations[] = 'Password must contain at least one digit';
        }

        if (!preg_match('/[^A-Za-z0-9]/', $password)) {
            $violations[] = 'Password must contain at least one special character';
        }

        if ($violations === []) {
            return PolicyResult::valid();
        }

        return PolicyResult::invalid($violations);
    }

    public function hash(string $plaintext): HashedPassword
    {
        return new HashedPassword(
            password_hash($plaintext, PASSWORD_BCRYPT, ['cost' => 12])
        );
    }

    public function verify(string $plaintext, HashedPassword $hash): bool
    {
        return password_verify($plaintext, $hash->hash);
    }
}
