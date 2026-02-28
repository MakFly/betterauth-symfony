<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Functional;

/**
 * Functional tests for TokenController endpoints.
 *
 * Tests: GET /auth/me, POST /auth/refresh, POST /auth/logout, POST /auth/revoke-all
 */
class TokenControllerFunctionalTest extends AbstractFunctionalTest
{
    // ========================================
    // ME TESTS
    // ========================================

    /**
     * @test
     */
    public function me_returns_user_data_when_authenticated(): void
    {
        $tokens = $this->registerUser('me_test@example.com', 'password123');
        $accessToken = $tokens['access_token'];

        $this->getJson('/auth/me', $this->bearerHeaders($accessToken));

        $this->assertStatusCode(200);
        $data = $this->getResponseData();
        $this->assertSame('me_test@example.com', $data['email']);
        $this->assertArrayHasKey('id', $data);
        $this->assertArrayHasKey('roles', $data);
    }

    /**
     * @test
     */
    public function me_returns_401_without_token(): void
    {
        $this->getJson('/auth/me');

        $this->assertStatusCode(401);
    }

    /**
     * @test
     */
    public function me_returns_401_with_invalid_token(): void
    {
        $this->getJson('/auth/me', ['Authorization' => 'Bearer invalid-token-xyz']);

        $this->assertStatusCode(401);
    }

    // ========================================
    // REFRESH TESTS
    // ========================================

    /**
     * @test
     */
    public function refresh_returns_new_tokens_with_valid_refresh_token(): void
    {
        $tokens = $this->registerUser('refresh_test@example.com', 'password123');
        $refreshToken = $tokens['refresh_token'];

        $this->postJson('/auth/refresh', ['refreshToken' => $refreshToken]);

        $this->assertStatusCode(200);
        $data = $this->getResponseData();
        $this->assertArrayHasKey('access_token', $data);
        $this->assertArrayHasKey('refresh_token', $data);
        // New refresh token should differ from the old one (rotation)
        $this->assertNotSame($refreshToken, $data['refresh_token']);
    }

    /**
     * @test
     */
    public function refresh_returns_400_without_refresh_token(): void
    {
        $this->postJson('/auth/refresh', []);

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertSame('Refresh token is required', $data['error']);
    }

    /**
     * @test
     */
    public function refresh_returns_401_with_invalid_token(): void
    {
        $this->postJson('/auth/refresh', ['refreshToken' => 'invalid-refresh-token']);

        $this->assertStatusCode(401);
    }

    /**
     * @test
     */
    public function refresh_token_cannot_be_reused(): void
    {
        $tokens = $this->registerUser('reuse_test@example.com', 'password123');
        $refreshToken = $tokens['refresh_token'];

        // First use — succeeds
        $this->postJson('/auth/refresh', ['refreshToken' => $refreshToken]);
        $this->assertStatusCode(200);

        // Second use of same token — should fail (token rotation)
        $this->postJson('/auth/refresh', ['refreshToken' => $refreshToken]);
        $this->assertStatusCode(401);
    }

    // ========================================
    // LOGOUT TESTS
    // ========================================

    /**
     * @test
     */
    public function logout_returns_200_and_invalidates_session(): void
    {
        $tokens = $this->registerUser('logout_test@example.com', 'password123');
        $accessToken = $tokens['access_token'];

        $this->postJson('/auth/logout', [], $this->bearerHeaders($accessToken));

        $this->assertStatusCode(200);
        $data = $this->getResponseData();
        $this->assertSame('Logged out successfully', $data['message']);
    }

    /**
     * @test
     */
    public function logout_returns_401_without_token(): void
    {
        $this->postJson('/auth/logout', []);

        $this->assertStatusCode(401);
    }

    // ========================================
    // REVOKE-ALL TESTS
    // ========================================

    /**
     * @test
     */
    public function revoke_all_returns_200_with_count(): void
    {
        $tokens = $this->registerUser('revoke_all_test@example.com', 'password123');
        $accessToken = $tokens['access_token'];

        $this->postJson('/auth/revoke-all', [], $this->bearerHeaders($accessToken));

        $this->assertStatusCode(200);
        $data = $this->getResponseData();
        $this->assertSame('All sessions revoked successfully', $data['message']);
        $this->assertArrayHasKey('count', $data);
    }

    /**
     * @test
     */
    public function revoke_all_returns_401_without_token(): void
    {
        $this->postJson('/auth/revoke-all', []);

        $this->assertStatusCode(401);
    }
}
