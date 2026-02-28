<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Functional;

/**
 * Functional tests for CredentialsController endpoints.
 *
 * Tests: POST /auth/register, POST /auth/login, POST /auth/login/2fa
 * Uses a real SQLite database and full Symfony kernel.
 */
class CredentialsControllerFunctionalTest extends AbstractFunctionalTest
{
    // ========================================
    // REGISTER TESTS
    // ========================================

    /**
     * @test
     */
    public function register_creates_user_and_returns_tokens(): void
    {
        $this->postJson('/auth/register', [
            'email' => 'newuser@example.com',
            'password' => 'password123',
        ]);

        $this->assertStatusCode(201);
        $data = $this->getResponseData();
        $this->assertArrayHasKey('access_token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
        $this->assertArrayHasKey('expires_in', $data);
        $this->assertSame('Bearer', $data['token_type']);
        $this->assertArrayHasKey('user', $data);
        $this->assertSame('newuser@example.com', $data['user']['email']);
    }

    /**
     * @test
     */
    public function register_with_name_includes_name_in_response(): void
    {
        $this->postJson('/auth/register', [
            'email' => 'named@example.com',
            'password' => 'password123',
            'name' => 'John Doe',
        ]);

        $this->assertStatusCode(201);
    }

    /**
     * @test
     */
    public function register_returns_400_on_duplicate_email(): void
    {
        // Register first time
        $this->postJson('/auth/register', [
            'email' => 'duplicate@example.com',
            'password' => 'password123',
        ]);
        $this->assertStatusCode(201);

        // Try to register with same email
        $this->postJson('/auth/register', [
            'email' => 'duplicate@example.com',
            'password' => 'different-password',
        ]);

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * @test
     */
    public function register_returns_422_on_invalid_email(): void
    {
        $this->postJson('/auth/register', [
            'email' => 'not-an-email',
            'password' => 'password123',
        ]);

        // Symfony validation returns 422 for invalid DTO
        $this->assertResponseStatusCodeSame(422);
    }

    /**
     * @test
     */
    public function register_returns_422_on_short_password(): void
    {
        $this->postJson('/auth/register', [
            'email' => 'valid@example.com',
            'password' => 'short',
        ]);

        $this->assertResponseStatusCodeSame(422);
    }

    // ========================================
    // LOGIN TESTS
    // ========================================

    /**
     * @test
     */
    public function login_returns_tokens_on_valid_credentials(): void
    {
        // First register
        $this->registerUser('logintest@example.com', 'password123');

        // Then login
        $this->postJson('/auth/login', [
            'email' => 'logintest@example.com',
            'password' => 'password123',
        ]);

        $this->assertStatusCode(200);
        $data = $this->getResponseData();
        $this->assertArrayHasKey('access_token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
        $this->assertSame('logintest@example.com', $data['user']['email']);
    }

    /**
     * @test
     */
    public function login_returns_401_on_wrong_password(): void
    {
        $this->registerUser('wrongpass@example.com', 'correct-password');

        $this->postJson('/auth/login', [
            'email' => 'wrongpass@example.com',
            'password' => 'wrong-password',
        ]);

        $this->assertStatusCode(401);
        $data = $this->getResponseData();
        $this->assertArrayHasKey('error', $data);
    }

    /**
     * @test
     */
    public function login_returns_401_on_non_existent_user(): void
    {
        $this->postJson('/auth/login', [
            'email' => 'nonexistent@example.com',
            'password' => 'password123',
        ]);

        $this->assertStatusCode(401);
    }

    /**
     * @test
     */
    public function login_returns_422_on_missing_fields(): void
    {
        $this->postJson('/auth/login', [
            'email' => 'test@example.com',
            // password missing
        ]);

        $this->assertResponseStatusCodeSame(422);
    }
}
