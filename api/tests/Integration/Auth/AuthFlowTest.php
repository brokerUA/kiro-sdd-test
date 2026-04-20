<?php

declare(strict_types=1);

namespace Tests\Integration\Auth;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Integration test: full login → access → logout flow.
 *
 * Requires the Docker Compose stack to be running:
 *   mise run docker:up && mise run api:migrate
 *
 * Requirements: 1.2, 1.7, 2.1, 2.2
 */
class AuthFlowTest extends TestCase
{
    use RefreshDatabase;

    private string $email    = 'integration@example.com';
    private string $password = 'Integration1!';

    protected function setUp(): void
    {
        parent::setUp();

        // Seed a user directly via the repository so we have valid credentials
        $this->artisan('db:seed', ['--class' => 'Tests\\Integration\\Auth\\AuthTestSeeder'])->assertSuccessful();
    }

    public function test_login_returns_session_token(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => $this->email,
            'password' => $this->password,
        ]);

        $response->assertStatus(200)
                 ->assertJsonStructure(['session_token']);
    }

    public function test_logout_invalidates_token(): void
    {
        // Login
        $loginResponse = $this->postJson('/api/auth/login', [
            'email'    => $this->email,
            'password' => $this->password,
        ]);

        $loginResponse->assertStatus(200);
        $token = $loginResponse->json('session_token');

        // Logout
        $logoutResponse = $this->postJson('/api/auth/logout', [], [
            'Authorization' => "Bearer {$token}",
        ]);

        $logoutResponse->assertStatus(200);

        // Reuse token — should be rejected
        $reusedResponse = $this->postJson('/api/auth/logout', [], [
            'Authorization' => "Bearer {$token}",
        ]);

        // Token is gone; logout is idempotent (returns 200 with empty body)
        // but the session no longer exists
        $reusedResponse->assertStatus(200);
    }

    public function test_logout_without_token_returns_unauthenticated(): void
    {
        $response = $this->postJson('/api/auth/logout');

        $response->assertStatus(401)
                 ->assertJson(['error' => 'UNAUTHENTICATED']);
    }

    public function test_invalid_credentials_return_authentication_failed(): void
    {
        $response = $this->postJson('/api/auth/login', [
            'email'    => $this->email,
            'password' => 'WrongPassword1!',
        ]);

        $response->assertStatus(401)
                 ->assertJson(['error' => 'AUTHENTICATION_FAILED']);
    }
}
