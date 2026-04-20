<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use Domain\Auth\Repositories\ResetTokenRepositoryInterface;
use Domain\Auth\Repositories\UserRepositoryInterface;
use Domain\Auth\Services\PasswordPolicy;
use Domain\Auth\ValueObjects\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * Integration test: concurrent reset token race condition.
 *
 * Asserts that only one active reset token exists per user at a time.
 * The DB UNIQUE constraint on user_id in password_reset_tokens enforces this.
 *
 * Requirements: 3.5
 */
class ResetTokenRaceConditionTest extends TestCase
{
    use RefreshDatabase;

    private string $email    = 'race@example.com';
    private string $password = 'RaceTest1!';

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->seedUser();
    }

    public function test_second_reset_request_invalidates_first_token(): void
    {
        $tokenRepo = $this->app->make(ResetTokenRepositoryInterface::class);

        // First request
        $this->postJson('/api/auth/password-reset/request', ['email' => $this->email])
             ->assertStatus(200);

        $firstRawToken = null;
        Queue::assertPushed(\Infrastructure\Auth\Queue\SendPasswordResetEmailJob::class, function ($job) use (&$firstRawToken) {
            $firstRawToken = $job->rawToken;
            return true;
        });

        Queue::fake(); // reset for second capture

        // Second request
        $this->postJson('/api/auth/password-reset/request', ['email' => $this->email])
             ->assertStatus(200);

        $secondRawToken = null;
        Queue::assertPushed(\Infrastructure\Auth\Queue\SendPasswordResetEmailJob::class, function ($job) use (&$secondRawToken) {
            $secondRawToken = $job->rawToken;
            return true;
        });

        $this->assertNotNull($firstRawToken);
        $this->assertNotNull($secondRawToken);
        $this->assertNotSame($firstRawToken, $secondRawToken);

        // First token should be gone
        $firstHash = hash('sha256', $firstRawToken);
        $this->assertNull($tokenRepo->findByTokenHash($firstHash));

        // Second token should be valid
        $secondHash = hash('sha256', $secondRawToken);
        $this->assertNotNull($tokenRepo->findByTokenHash($secondHash));
    }

    public function test_only_one_token_row_exists_per_user(): void
    {
        // Fire two requests
        $this->postJson('/api/auth/password-reset/request', ['email' => $this->email]);
        $this->postJson('/api/auth/password-reset/request', ['email' => $this->email]);

        $userRepo = $this->app->make(UserRepositoryInterface::class);
        $user     = $userRepo->findByEmail(new Email($this->email));

        $count = \Illuminate\Support\Facades\DB::table('password_reset_tokens')
            ->where('user_id', $user->id)
            ->count();

        $this->assertSame(1, $count);
    }

    private function seedUser(): void
    {
        $userRepo      = $this->app->make(UserRepositoryInterface::class);
        $passwordPolicy = new PasswordPolicy();

        $user = new \Domain\Auth\Entities\User(
            id:           (string) \Illuminate\Support\Str::uuid(),
            email:        new Email($this->email),
            passwordHash: $passwordPolicy->hash($this->password),
            createdAt:    new \DateTimeImmutable(),
            updatedAt:    new \DateTimeImmutable(),
        );

        $userRepo->save($user);
    }
}
