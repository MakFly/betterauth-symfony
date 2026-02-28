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
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

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
    private MockObject&CacheItemPoolInterface $cache;
    private OAuthController $controller;

    protected function setUp(): void
    {
        $this->authManager = $this->createMock(AuthManager::class);
        $this->oauthManager = $this->createMock(OAuthManager::class);
        $this->totpProvider = $this->createMock(TotpProvider::class);
        $this->cache = $this->createMock(CacheItemPoolInterface::class);

        $this->controller = new OAuthController(
            $this->authManager,
            $this->oauthManager,
            $this->totpProvider,
            $this->cache,
            null,
            'http://localhost:5173',
        );
        $this->setUpControllerContainer($this->controller);
    }

    private function createCacheItem(bool $isHit = false, mixed $value = null): MockObject&CacheItemInterface
    {
        $item = $this->createMock(CacheItemInterface::class);
        $item->method('isHit')->willReturn($isHit);
        $item->method('get')->willReturn($value);
        return $item;
    }

    private function setupCacheStore(): void
    {
        $item = $this->createCacheItem(false);
        $item->method('set')->willReturnSelf();
        $item->method('expiresAfter')->willReturnSelf();
        $this->cache->method('getItem')->willReturn($item);
        $this->cache->method('save')->willReturn(true);
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

        $request = new Request();
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

        $this->setupCacheStore();

        $request = new Request();
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

        $this->setupCacheStore();

        $request = new Request(['redirect' => 'true']);
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

        $request = new Request();
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

        $this->setupCacheStore();

        $response = $this->controller->url('github');

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
        $stateHash = hash('sha256', $state);
        $cacheKey = 'better_auth.oauth_state.' . $stateHash;

        $cacheItem = $this->createCacheItem(true, ['provider' => 'google', 'created_at' => time()]);
        $this->cache->method('getItem')->with($cacheKey)->willReturn($cacheItem);
        $this->cache->method('deleteItem')->willReturn(true);

        $this->oauthManager->method('handleCallback')
            ->willReturn([
                'user' => ['id' => 'uuid-1', 'email' => 'test@example.com'],
                'access_token' => 'oauth-access-token',
                'refresh_token' => 'oauth-refresh-token',
                'expires_in' => 3600,
            ]);

        $this->totpProvider->method('requires2fa')->willReturn(false);

        $request = new Request(['code' => 'valid-code', 'state' => $state]);
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
        $request = new Request(['state' => 'some-state']);
        $response = $this->controller->callback('google', $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('error=', $response->getTargetUrl());
    }

    /**
     * @test
     */
    public function callback_redirects_with_error_when_state_invalid(): void
    {
        $cacheItem = $this->createCacheItem(false);
        $this->cache->method('getItem')->willReturn($cacheItem);

        $request = new Request(['code' => 'some-code', 'state' => 'invalid-state']);
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
        $stateHash = hash('sha256', $state);

        $cacheItem = $this->createCacheItem(true, ['provider' => 'google', 'created_at' => time()]);
        $this->cache->method('getItem')->with('better_auth.oauth_state.' . $stateHash)->willReturn($cacheItem);
        $this->cache->method('deleteItem')->willReturn(true);

        $this->oauthManager->method('handleCallback')
            ->willReturn([
                'user' => ['id' => 'uuid-1', 'email' => 'user@example.com'],
                'session' => null,
            ]);

        $this->totpProvider->method('requires2fa')->with('uuid-1')->willReturn(true);

        $request = new Request(['code' => 'auth-code', 'state' => $state]);
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
        $stateHash = hash('sha256', $state);

        $cacheItem = $this->createCacheItem(true, ['provider' => 'google', 'created_at' => time()]);
        $this->cache->method('getItem')->with('better_auth.oauth_state.' . $stateHash)->willReturn($cacheItem);
        $this->cache->method('deleteItem')->willReturn(true);

        $this->oauthManager->method('handleCallback')
            ->willThrowException(new \Exception('OAuth token exchange failed'));

        $request = new Request(['code' => 'invalid-code', 'state' => $state]);
        $response = $this->controller->callback('google', $request);

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertStringContainsString('error=', $response->getTargetUrl());
    }
}
