<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Controller;

use BetterAuth\Core\AuthManager;
use BetterAuth\Providers\OAuthProvider\OAuthManager;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use BetterAuth\Symfony\Controller\OAuthController;
use BetterAuth\Symfony\Tests\Controller\Trait\ControllerTestTrait;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

/**
 * Unit tests for OAuthController.
 *
 * Tests: providers list, redirect to provider, get URL, OAuth callback.
 */
class OAuthControllerTest extends TestCase
{
    use ControllerTestTrait;

    private MockObject&AuthManager $authManager;
    private MockObject&OAuthManager $oauthManager;
    private MockObject&TotpProvider $totpProvider;
    private OAuthController $controller;

    protected function setUp(): void
    {
        $this->authManager = $this->createMock(AuthManager::class);
        $this->oauthManager = $this->createMock(OAuthManager::class);
        $this->totpProvider = $this->createMock(TotpProvider::class);

        $this->controller = new OAuthController(
            $this->authManager,
            $this->oauthManager,
            $this->totpProvider,
            null,
            'http://localhost:5173',
        );
        $this->setUpControllerContainer($this->controller);
    }

    private function createRequestWithSession(array $query = []): Request
    {
        $request = new Request($query);
        $request->setSession(new Session(new MockArraySessionStorage()));
        return $request;
    }

    private function storeStateInSession(Request $request, string $provider, string $state): void
    {
        $request->getSession()->set('better_auth.oauth_state.' . $provider, [
            'state' => hash('sha256', $state),
            'created_at' => time(),
        ]);
    }

    // ========================================
    // PROVIDERS TESTS
    // ========================================

    /**
     * @test
     */
    public function providers_returns_list_of_available_providers(): void
    {
        $this->oauthManager->method('getAvailableProviders')
            ->willReturn(['google', 'github', 'facebook']);

        $response = $this->controller->providers();

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertSame(['google', 'github', 'facebook'], $data['providers']);
    }

    /**
     * @test
     */
    public function providers_returns_400_on_exception(): void
    {
        $this->oauthManager->method('getAvailableProviders')
            ->willThrowException(new \Exception('OAuth not configured'));

        $response = $this->controller->providers();

        $this->assertSame(400, $response->getStatusCode());
    }

    // ========================================
    // REDIRECT TO PROVIDER TESTS
    // ========================================

    /**
     * @test
     */
    public function redirect_to_provider_returns_json_url_by_default(): void
    {
        $this->oauthManager->method('getAuthorizationUrl')
            ->with('google')
            ->willReturn([
                'url' => 'https://accounts.google.com/oauth2/auth?state=abc123',
                'state' => 'abc123',
            ]);

        $request = $this->createRequestWithSession();
        $response = $this->controller->redirectToProvider('google', $request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertArrayHasKey('url', $data);
        $this->assertArrayHasKey('state', $data);
    }

    /**
     * @test
     */
    public function redirect_to_provider_redirects_when_redirect_param_true(): void
    {
        $this->oauthManager->method('getAuthorizationUrl')
            ->willReturn([
                'url' => 'https://accounts.google.com/oauth2/auth?state=abc123',
                'state' => 'abc123',
            ]);

        $request = $this->createRequestWithSession(['redirect' => 'true']);
        $response = $this->controller->redirectToProvider('google', $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('https://accounts.google.com/oauth2/auth?state=abc123', $response->getTargetUrl());
    }

    /**
     * @test
     */
    public function redirect_to_provider_returns_400_on_unknown_provider(): void
    {
        $this->oauthManager->method('getAuthorizationUrl')
            ->willThrowException(new \Exception('Provider not found: unknown'));

        $request = $this->createRequestWithSession();
        $response = $this->controller->redirectToProvider('unknown', $request);

        $this->assertSame(400, $response->getStatusCode());
    }

    // ========================================
    // URL TESTS
    // ========================================

    /**
     * @test
     */
    public function url_returns_authorization_url(): void
    {
        $this->oauthManager->method('getAuthorizationUrl')
            ->with('github')
            ->willReturn([
                'url' => 'https://github.com/login/oauth/authorize?state=xyz',
                'state' => 'xyz',
            ]);

        $request = $this->createRequestWithSession();
        $response = $this->controller->url('github', $request);

        $this->assertSame(200, $response->getStatusCode());
        $data = json_decode($response->getContent(), true);
        $this->assertStringContainsString('github.com', $data['url']);
    }

    // ========================================
    // CALLBACK TESTS
    // ========================================

    /**
     * @test
     */
    public function callback_redirects_to_frontend_with_tokens_on_success(): void
    {
        $state = 'valid-state-123';

        $this->oauthManager->method('handleCallback')
            ->willReturn([
                'user' => ['id' => 'uuid-1', 'email' => 'test@example.com'],
                'access_token' => 'oauth-access-token',
                'refresh_token' => 'oauth-refresh-token',
                'expires_in' => 3600,
            ]);

        $this->totpProvider->method('requires2fa')->willReturn(false);

        $request = $this->createRequestWithSession(['code' => 'valid-code', 'state' => $state]);
        $this->storeStateInSession($request, 'google', $state);

        $response = $this->controller->callback('google', $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $targetUrl = $response->getTargetUrl();
        $this->assertStringContainsString('localhost:5173', $targetUrl);
        $this->assertStringContainsString('oauth-access-token', $targetUrl);
    }

    /**
     * @test
     */
    public function callback_redirects_with_error_when_no_code(): void
    {
        $request = $this->createRequestWithSession(['state' => 'some-state']);
        $response = $this->controller->callback('google', $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('error=', $response->getTargetUrl());
    }

    /**
     * @test
     */
    public function callback_redirects_with_error_when_state_invalid(): void
    {
        $request = $this->createRequestWithSession(['code' => 'some-code', 'state' => 'invalid-state']);
        $response = $this->controller->callback('google', $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('Invalid+OAuth+state', $response->getTargetUrl());
    }

    /**
     * @test
     */
    public function callback_redirects_with_2fa_required_when_totp_enabled(): void
    {
        $state = 'valid-state';

        $this->oauthManager->method('handleCallback')
            ->willReturn([
                'user' => ['id' => 'uuid-1', 'email' => 'user@example.com'],
                'session' => null,
            ]);

        $this->totpProvider->method('requires2fa')->with('uuid-1')->willReturn(true);

        $request = $this->createRequestWithSession(['code' => 'auth-code', 'state' => $state]);
        $this->storeStateInSession($request, 'google', $state);

        $response = $this->controller->callback('google', $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('requires2fa=true', $response->getTargetUrl());
    }

    /**
     * @test
     */
    public function callback_redirects_with_error_on_provider_exception(): void
    {
        $state = 'valid-state';

        $this->oauthManager->method('handleCallback')
            ->willThrowException(new \Exception('OAuth token exchange failed'));

        $request = $this->createRequestWithSession(['code' => 'invalid-code', 'state' => $state]);
        $this->storeStateInSession($request, 'google', $state);

        $response = $this->controller->callback('google', $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('error=', $response->getTargetUrl());
    }
}
