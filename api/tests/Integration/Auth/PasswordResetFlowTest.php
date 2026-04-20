<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use Domain\Auth\Repositories\ResetTokenRepositoryInterface;
use Domain\Auth\Repositories\SessionRepositoryInterface;
use Domain\Auth\Repositories\UserRepositoryInterface;
use Domain\Auth\ValueObjects\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Infrastructure\Auth\Queue\SendPasswordResetEmailJob;
use Tests\TestCase;

/**
 * Integration test: full password reset flow.
 *
 * Requirements: 3.1, 3.4, 4.1, 4.6
 */
class PasswordResetFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $email       = 'reset@example.com';
    private string $oldPassword = 'OldPassword1!';
    private string $newPassword = 'NewPassword2@';

    public function test_password_reset_request_dispatches_job(): void
    {
        Queue::fake();

        $this->seedUser();

        $response = $this->postJson('/api/auth/password-reset/request', [
            'email' => $this->email,
        ]);

        $response->assertStatus(200);
        Queue::assertPushed(SendPasswordResetEmailJob::class);
    }

    public function test_unregistered_email_returns_200_without_dispatching(): void
    {
        Queue::fake();

        $response = $this->postJson('/api/auth/password-reset/request', [
            'email' => 'nobody@example.com',
        ]);

        $response->assertStatus(200);
        Queue::assertNotPushed(SendPasswordResetEmailJob::class);
    }

    public function test_complete_reset_allows_login_with_new_password(): void
    {
        Queue::fake();

        $this->seedUser();

        // Request reset
        $this->postJson('/api/auth/password-reset/request', ['email' => $this->email])
             ->assertStatus(200);

        // Retrieve token from repository
        $userRepo  = $this->app->make(UserRepositoryInterface::class);
        $tokenRepo = $this->app->make(ResetTokenRepositoryInterface::class);
        $user      = $userRepo->findByEmail(new Email($this->email));
        $this->assertNotNull($user);

        // We need the raw token — capture it from the dispatched job
        $rawToken = null;
        Queue::assertPushed(SendPasswordResetEmailJob::class, function (SendPasswordResetEmailJob $job) use (&$rawToken) {
            $rawToken = $job->rawToken;
            return true;
        });

        $this->assertNotNull($rawToken);

        // Complete reset
        $this->postJson('/api/auth/password-reset/complete', [
            'token'        => $rawToken,
            'new_password' => $this->newPassword,
        ])->assertStatus(200);

        // Old password no longer works
        $this->postJson('/api/auth/login', [
            'email'    => $this->email,
            'password' => $this->oldPassword,
        ])->assertStatus(401);

        // New password works
        $this->postJson('/api/auth/login', [
            'email'    => $this->email,
            'password' => $this->newPassword,
        ])->assertStatus(200)->assertJsonStructure(['session_token']);
    }

    public function test_reset_invalidates_all_existing_sessions(): void
    {
        Queue::fake();

        $this->seedUser();

        // Login to create a session
        $loginResponse = $this->postJson('/api/auth/login', [
            'email'    => $this->email,
            'password' => $this->oldPassword,
        ])->assertStatus(200);

        $sessionToken = $loginResponse->json('session_token');

        // Request and complete reset
        $this->postJson('/api/auth/password-reset/request', ['email' => $this->email]);

        $rawToken = null;
        Queue::assertPushed(SendPasswordResetEmailJob::class, function (SendPasswordResetEmailJob $job) use (&$rawToken) {
            $rawToken = $job->rawToken;
            return true;
        });

        $this->postJson('/api/auth/password-reset/complete', [
            'token'        => $rawToken,
            'new_password' => $this->newPassword,
        ])->assertStatus(200);

        // Old session token should now be invalid
        $sessionRepo = $this->app->make(SessionRepositoryInterface::class);
        $tokenHash   = hash('sha256', $sessionToken);
        $this->assertNull($sessionRepo->findByTokenHash($tokenHash));
    }

    private function seedUser(): void
    {
        $userRepo      = $this->app->make(UserRepositoryInterface::class);
        $passwordPolicy = new \Domain\Auth\Services\PasswordPolicy();

        $user = new \Domain\Auth\Entities\User(
            id:           (string) \Illuminate\Support\Str::uuid(),
            email:        new Email($this->email),
            passwordHash: $passwordPolicy->hash($this->oldPassword),
            createdAt:    new \DateTimeImmutable(),
            updatedAt:    new \DateTimeImmutable(),
        );

        $userRepo->save($user);
    }
}
