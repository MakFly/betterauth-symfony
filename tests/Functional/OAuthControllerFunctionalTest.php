<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Functional;

/**
 * Functional tests for OAuthController endpoints.
 *
 * Tests: GET /auth/oauth/providers, GET /auth/oauth/{provider}, GET /auth/oauth/{provider}/url,
 *        GET /auth/oauth/{provider}/callback
 */
class OAuthControllerFunctionalTest extends AbstractFunctionalTest
{
    // ========================================
    // PROVIDERS TESTS
    // ========================================

    /**
     * @test
     */
    public function providers_returns_empty_list_when_no_oauth_configured(): void
    {
        $this->getJson('/auth/oauth/providers');

        $this->assertStatusCode(200);
        $data = $this->getResponseData();
        $this->assertArrayHasKey('providers', $data);
        $this->assertIsArray($data['providers']);
    }

    // ========================================
    // REDIRECT TO PROVIDER TESTS
    // ========================================

    /**
     * @test
     */
    public function redirect_to_unknown_provider_returns_400(): void
    {
        $this->getJson('/auth/oauth/unknownprovider');

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertArrayHasKey('error', $data);
    }

    // ========================================
    // URL TESTS
    // ========================================

    /**
     * @test
     */
    public function url_returns_400_for_unknown_provider(): void
    {
        $this->getJson('/auth/oauth/unknownprovider/url');

        $this->assertStatusCode(400);
        $data = $this->getResponseData();
        $this->assertArrayHasKey('error', $data);
    }

    // ========================================
    // CALLBACK TESTS
    // ========================================

    /**
     * @test
     */
    public function callback_redirects_with_error_when_no_code(): void
    {
        $this->client->request('GET', '/auth/oauth/google/callback');

        // Callback without code should redirect to frontend with error
        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('error=', $location);
    }

    /**
     * @test
     */
    public function callback_redirects_with_error_when_state_invalid(): void
    {
        $this->client->request('GET', '/auth/oauth/google/callback', [
            'code' => 'some-code',
            'state' => 'invalid-state',
        ]);

        $this->assertResponseRedirects();
        $location = $this->client->getResponse()->headers->get('Location');
        $this->assertStringContainsString('Invalid+OAuth+state', $location);
    }
}
