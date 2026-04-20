<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use DateTimeImmutable;
use Domain\Auth\Repositories\SessionRepositoryInterface;
use Domain\Auth\Repositories\UserRepositoryInterface;
use Domain\Auth\ValueObjects\Email;
use Domain\Auth\ValueObjects\SessionToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration test: session expiry.
 *
 * Requirements: 2.4, 2.5
 */
class SessionExpiryTest extends TestCase
{
    use RefreshDatabase;

    public function test_expired_session_is_not_found(): void
    {
        $sessionRepo = $this->app->make(SessionRepositoryInterface::class);

        $raw   = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $raw);
        $token = new SessionToken($raw, $hash);

        // Create a session that expired 1 second ago
        $sessionRepo->create(
            userId:    (string) \Illuminate\Support\Str::uuid(),
            token:     $token,
            expiresAt: new DateTimeImmutable('-1 second'),
        );

        // Purge expired sessions
        $sessionRepo->purgeExpired(new DateTimeImmutable());

        // Session should no longer be findable
        $this->assertNull($sessionRepo->findByTokenHash($hash));
    }

    public function test_active_session_is_found(): void
    {
        $sessionRepo = $this->app->make(SessionRepositoryInterface::class);

        $raw   = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $raw);
        $token = new SessionToken($raw, $hash);

        // Create a session that expires in 1 hour
        $sessionRepo->create(
            userId:    (string) \Illuminate\Support\Str::uuid(),
            token:     $token,
            expiresAt: new DateTimeImmutable('+1 hour'),
        );

        $this->assertNotNull($sessionRepo->findByTokenHash($hash));
    }

    public function test_session_is_expired_after_inactivity_timeout(): void
    {
        $sessionRepo = $this->app->make(SessionRepositoryInterface::class);

        $raw   = bin2hex(random_bytes(32));
        $hash  = hash('sha256', $raw);
        $token = new SessionToken($raw, $hash);

        $session = $sessionRepo->create(
            userId:    (string) \Illuminate\Support\Str::uuid(),
            token:     $token,
            expiresAt: new DateTimeImmutable('+24 hours'),
        );

        // Simulate inactivity: last activity was 31 minutes ago
        $inactivityTimeout = 1800; // 30 minutes
        $now               = new DateTimeImmutable('+31 minutes');

        $this->assertTrue($session->isExpired($now, $inactivityTimeout));
    }
}
