<?php

declare(strict_types=1);

namespace Infrastructure\Auth\RateLimiting;

use Domain\Auth\ValueObjects\Email;
use Illuminate\Support\Facades\Cache;

final class RedisRateLimiter
{
    private const int MAX_ATTEMPTS = 5;
    private const int WINDOW_SECONDS = 900; // 15 minutes

    private function attemptsKey(Email $email): string
    {
        return 'auth.failed:' . hash('sha256', $email->value);
    }

    private function expiryKey(Email $email): string
    {
        return 'auth.failed.expiry:' . hash('sha256', $email->value);
    }

    public function recordFailure(Email $email): void
    {
        $attemptsKey = $this->attemptsKey($email);
        $expiryKey   = $this->expiryKey($email);

        $attempts = (int) Cache::get($attemptsKey, 0);
        $attempts++;

        // On the first failure, record when the window expires
        if ($attempts === 1) {
            Cache::put($expiryKey, time() + self::WINDOW_SECONDS, self::WINDOW_SECONDS);
        }

        Cache::put($attemptsKey, $attempts, self::WINDOW_SECONDS);
    }

    public function isLocked(Email $email): LockStatus
    {
        $attempts = (int) Cache::get($this->attemptsKey($email), 0);

        if ($attempts < self::MAX_ATTEMPTS) {
            return LockStatus::unlocked();
        }

        $expiresAt    = (int) Cache::get($this->expiryKey($email), time());
        $retryAfter   = max(1, $expiresAt - time());

        return LockStatus::locked($retryAfter);
    }

    public function clearAttempts(Email $email): void
    {
        Cache::forget($this->attemptsKey($email));
        Cache::forget($this->expiryKey($email));
    }
}
