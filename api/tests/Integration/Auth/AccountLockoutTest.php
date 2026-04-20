<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use Domain\Auth\Repositories\UserRepositoryInterface;
use Domain\Auth\Services\PasswordPolicy;
use Domain\Auth\ValueObjects\Email;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * Integration test: account lockout and unlock.
 *
 * Requirements: 1.8
 */
class AccountLockoutTest extends TestCase
{
    use RefreshDatabase;

    private string $email    = 'lockout@example.com';
    private string $password = 'Correct1!';

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        $this->seedUser();
    }

    public function test_account_locked_after_5_failed_attempts(): void
    {
        // 5 consecutive failed attempts
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/auth/login', [
                'email'    => $this->email,
                'password' => 'WrongPassword1!',
            ])->assertStatus(401);
        }

        // 6th attempt should return 423 ACCOUNT_LOCKED
        $response = $this->postJson('/api/auth/login', [
            'email'    => $this->email,
            'password' => 'WrongPassword1!',
        ]);

        $response->assertStatus(423)
                 ->assertJson(['error' => 'ACCOUNT_LOCKED'])
                 ->assertJsonStructure(['retry_after_seconds']);
    }

    public function test_successful_login_clears_failed_attempts(): void
    {
        // 4 failed attempts
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/login', [
                'email'    => $this->email,
                'password' => 'WrongPassword1!',
            ])->assertStatus(401);
        }

        // Successful login clears counter
        $this->postJson('/api/auth/login', [
            'email'    => $this->email,
            'password' => $this->password,
        ])->assertStatus(200);

        // 5 more failed attempts should not lock yet (counter was reset)
        for ($i = 0; $i < 4; $i++) {
            $this->postJson('/api/auth/login', [
                'email'    => $this->email,
                'password' => 'WrongPassword1!',
            ])->assertStatus(401);
        }

        // 5th attempt after reset — still not locked
        $this->postJson('/api/auth/login', [
            'email'    => $this->email,
            'password' => 'WrongPassword1!',
        ])->assertStatus(401);
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
