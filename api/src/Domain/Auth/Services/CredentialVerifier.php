<?php

declare(strict_types=1);

namespace Domain\Auth\Services;

use Domain\Auth\Repositories\UserRepositoryInterface;
use Domain\Auth\ValueObjects\Email;

class CredentialVerifier
{
    /**
     * Verify an email/password pair against stored credentials.
     *
     * Returns VerifyResult::failure() for both unknown email and wrong password —
     * the response is intentionally identical to prevent user enumeration (requirements 1.3, 1.4).
     */
    public function verify(Email $email, string $plainPassword, UserRepositoryInterface $userRepo): VerifyResult
    {
        $user = $userRepo->findByEmail($email);

        if ($user === null) {
            return VerifyResult::failure();
        }

        if (!password_verify($plainPassword, $user->passwordHash->hash)) {
            return VerifyResult::failure();
        }

        return VerifyResult::success($user);
    }
}
