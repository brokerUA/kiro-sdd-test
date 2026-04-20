<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use Domain\Auth\Repositories\UserRepositoryInterface;
use Domain\Auth\Services\PasswordPolicy;
use Domain\Auth\ValueObjects\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Infrastructure\Auth\Mail\PasswordResetMail;
use Tests\TestCase;

/**
 * Integration test: queue worker delivers reset email.
 *
 * Uses Mail::fake() to assert the mailable is sent when the job is processed.
 *
 * Requirements: 3.1
 */
class QueueWorkerEmailTest extends TestCase
{
    use RefreshDatabase;

    private string $email    = 'worker@example.com';
    private string $password = 'Worker1!';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        $this->seedUser();
    }

    public function test_password_reset_email_is_sent_when_job_is_processed(): void
    {
        // Run the job synchronously (QUEUE_CONNECTION=sync in test env)
        $this->postJson('/api/auth/password-reset/request', [
            'email' => $this->email,
        ])->assertStatus(200);

        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) {
            return $mail->hasTo($this->email);
        });
    }

    public function test_reset_email_contains_valid_reset_link(): void
    {
        $this->postJson('/api/auth/password-reset/request', [
            'email' => $this->email,
        ])->assertStatus(200);

        Mail::assertSent(PasswordResetMail::class, function (PasswordResetMail $mail) {
            // The raw token must be a 64-char hex string
            return strlen($mail->rawToken) === 64 && ctype_xdigit($mail->rawToken);
        });
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
