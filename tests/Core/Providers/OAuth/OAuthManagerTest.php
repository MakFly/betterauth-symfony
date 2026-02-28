<?php

declare(strict_types=1);

namespace BetterAuth\Core\Tests\Providers\OAuth;

use BetterAuth\Core\Config\AuthConfig;
use BetterAuth\Core\Entities\ProviderUser;
use BetterAuth\Core\Entities\Session;
use BetterAuth\Core\Entities\User;
use BetterAuth\Core\Interfaces\OAuthProviderInterface;
use BetterAuth\Core\Interfaces\SessionRepositoryInterface;
use BetterAuth\Core\Interfaces\TokenManagerInterface;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use BetterAuth\Core\SessionService;
use BetterAuth\Providers\OAuthProvider\OAuthManager;
use BetterAuth\Tests\Fixtures\TestUser;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for OAuthManager.
 *
 * Covers:
 * - handleCallback() with valid/invalid state
 * - Email linking (existing user)
 * - New user creation via OAuth
 * - Provider errors handling
 * - Token mode (API) vs session mode
 */
class OAuthManagerTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private SessionRepositoryInterface&MockObject $sessionRepository;
    private SessionService $sessionService;
    private OAuthProviderInterface&MockObject $oauthProvider;
    private OAuthManager $oauthManager;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->sessionRepository = $this->createMock(SessionRepositoryInterface::class);

        // SessionService is final — build a real one with a mocked repository
        $this->sessionService = new SessionService(
            sessionRepository: $this->sessionRepository,
        );

        $this->oauthProvider = $this->createMock(OAuthProviderInterface::class);
        $this->oauthProvider->method('getName')->willReturn('google');

        $this->oauthManager = new OAuthManager(
            $this->userRepository,
            $this->sessionService,
        );
        $this->oauthManager->addProvider($this->oauthProvider);
    }

    // ========================================
    // PROVIDER REGISTRATION TESTS
    // ========================================

    public function testAddProviderRegistersProvider(): void
    {
        $this->assertTrue($this->oauthManager->hasProvider('google'));
    }

    public function testHasProviderReturnsFalseForUnknownProvider(): void
    {
        $this->assertFalse($this->oauthManager->hasProvider('facebook'));
    }

    public function testGetAvailableProvidersReturnsRegisteredProviders(): void
    {
        $providers = $this->oauthManager->getAvailableProviders();
        $this->assertContains('google', $providers);
    }

    // ========================================
    // AUTHORIZATION URL TESTS
    // ========================================

    public function testGetAuthorizationUrlReturnsUrlAndState(): void
    {
        $this->oauthProvider
            ->expects($this->once())
            ->method('getAuthorizationUrl')
            ->willReturn('https://accounts.google.com/oauth/authorize?state=abc');

        $result = $this->oauthManager->getAuthorizationUrl('google');

        $this->assertArrayHasKey('url', $result);
        $this->assertArrayHasKey('state', $result);
        $this->assertNotEmpty($result['state']);
        $this->assertSame('https://accounts.google.com/oauth/authorize?state=abc', $result['url']);
    }

    public function testGetAuthorizationUrlThrowsForUnknownProvider(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageMatches("/OAuth provider 'unknown' not found/");

        $this->oauthManager->getAuthorizationUrl('unknown');
    }

    // ========================================
    // HANDLE CALLBACK — STATE VALIDATION TESTS
    // ========================================

    public function testHandleCallbackThrowsOnInvalidState(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid OAuth state parameter');

        $this->oauthManager->handleCallback(
            providerName: 'google',
            code: 'auth-code',
            redirectUri: 'https://example.com/callback',
            ipAddress: '127.0.0.1',
            userAgent: 'TestBrowser/1.0',
            state: 'invalid-state',
            expectedState: 'expected-state',
        );
    }

    public function testHandleCallbackSucceedsWithValidState(): void
    {
        $providerUser = new ProviderUser(
            providerId: 'google-123',
            email: 'user@example.com',
            name: 'Test User',
            avatar: null,
            emailVerified: true,
            rawData: [],
        );

        $this->oauthProvider->method('getAccessToken')->willReturn('access-token');
        $this->oauthProvider->method('getUserInfo')->willReturn($providerUser);

        $user = $this->createMockUser('user-1', 'user@example.com');
        $this->userRepository->method('findByProvider')->willReturn($user);

        $session = $this->createMock(Session::class);
        $this->sessionRepository->method('create')->willReturn($session);

        $result = $this->oauthManager->handleCallback(
            providerName: 'google',
            code: 'auth-code',
            redirectUri: 'https://example.com/callback',
            ipAddress: '127.0.0.1',
            userAgent: 'TestBrowser/1.0',
            state: 'valid-state',
            expectedState: 'valid-state',
        );

        $this->assertArrayHasKey('user', $result);
        $this->assertArrayHasKey('session', $result);
        $this->assertFalse($result['isNewUser']);
    }

    public function testHandleCallbackSkipsStateValidationWhenNull(): void
    {
        $providerUser = new ProviderUser(
            providerId: 'google-456',
            email: 'user2@example.com',
            name: 'Another User',
            avatar: null,
            emailVerified: true,
            rawData: [],
        );

        $this->oauthProvider->method('getAccessToken')->willReturn('access-token');
        $this->oauthProvider->method('getUserInfo')->willReturn($providerUser);

        $user = $this->createMockUser('user-2', 'user2@example.com');
        $this->userRepository->method('findByProvider')->willReturn($user);

        $session = $this->createMock(Session::class);
        $this->sessionRepository->method('create')->willReturn($session);

        // No state validation when both are null
        $result = $this->oauthManager->handleCallback(
            providerName: 'google',
            code: 'auth-code',
            redirectUri: 'https://example.com/callback',
            ipAddress: '127.0.0.1',
            userAgent: 'TestBrowser/1.0',
            state: null,
            expectedState: null,
        );

        $this->assertArrayHasKey('user', $result);
    }

    // ========================================
    // HANDLE CALLBACK — NEW USER CREATION
    // ========================================

    public function testHandleCallbackCreatesNewUserWhenNotFound(): void
    {
        $providerUser = new ProviderUser(
            providerId: 'google-new',
            email: 'newuser@example.com',
            name: 'New User',
            avatar: 'https://avatar.url/pic.jpg',
            emailVerified: true,
            rawData: [],
        );

        $this->oauthProvider->method('getAccessToken')->willReturn('access-token');
        $this->oauthProvider->method('getUserInfo')->willReturn($providerUser);

        // Not found by provider, not found by email
        $this->userRepository->method('findByProvider')->willReturn(null);
        $this->userRepository->method('findByEmail')->willReturn(null);
        $this->userRepository->method('generateId')->willReturn('new-user-uuid');

        $newUser = $this->createMockUser('new-user-uuid', 'newuser@example.com');
        $this->userRepository->expects($this->once())
            ->method('create')
            ->willReturn($newUser);

        $session = $this->createMock(Session::class);
        $this->sessionRepository->method('create')->willReturn($session);

        $result = $this->oauthManager->handleCallback(
            providerName: 'google',
            code: 'auth-code',
            redirectUri: 'https://example.com/callback',
            ipAddress: '127.0.0.1',
            userAgent: 'TestBrowser/1.0',
        );

        $this->assertTrue($result['isNewUser']);
    }

    public function testHandleCallbackCreatesNewUserWithNullIdForAutoIncrement(): void
    {
        $providerUser = new ProviderUser(
            providerId: 'google-autoinc',
            email: 'autoinc@example.com',
            name: 'AutoInc User',
            avatar: null,
            emailVerified: true,
            rawData: [],
        );

        $this->oauthProvider->method('getAccessToken')->willReturn('access-token');
        $this->oauthProvider->method('getUserInfo')->willReturn($providerUser);

        $this->userRepository->method('findByProvider')->willReturn(null);
        $this->userRepository->method('findByEmail')->willReturn(null);
        // generateId returns null for auto-increment
        $this->userRepository->method('generateId')->willReturn(null);

        $newUser = $this->createMockUser('1', 'autoinc@example.com');
        $this->userRepository->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data): bool {
                // Should NOT contain 'id' key when generateId returns null
                return !array_key_exists('id', $data);
            }))
            ->willReturn($newUser);

        $session = $this->createMock(Session::class);
        $this->sessionRepository->method('create')->willReturn($session);

        $result = $this->oauthManager->handleCallback(
            providerName: 'google',
            code: 'auth-code',
            redirectUri: 'https://example.com/callback',
            ipAddress: '127.0.0.1',
            userAgent: 'TestBrowser/1.0',
        );

        $this->assertTrue($result['isNewUser']);
    }

    // ========================================
    // HANDLE CALLBACK — EMAIL LINKING
    // ========================================

    public function testHandleCallbackLinksExistingUserWithVerifiedEmail(): void
    {
        $providerUser = new ProviderUser(
            providerId: 'google-link',
            email: 'existing@example.com',
            name: 'Existing User',
            avatar: null,
            emailVerified: true,
            rawData: [],
        );

        $this->oauthProvider->method('getAccessToken')->willReturn('access-token');
        $this->oauthProvider->method('getUserInfo')->willReturn($providerUser);

        // Not found by provider (new OAuth account), but found by email
        $this->userRepository->method('findByProvider')->willReturn(null);

        // Use TestUser (concrete) because OAuthManager accesses $user->metadata directly
        $existingUser = TestUser::fromArray(['id' => 'existing-user-1', 'email' => 'existing@example.com']);
        $this->userRepository->method('findByEmail')->willReturn($existingUser);

        $updatedUser = $this->createMockUser('existing-user-1', 'existing@example.com');
        $this->userRepository->expects($this->once())
            ->method('update')
            ->willReturn($updatedUser);

        $session = $this->createMock(Session::class);
        $this->sessionRepository->method('create')->willReturn($session);

        $result = $this->oauthManager->handleCallback(
            providerName: 'google',
            code: 'auth-code',
            redirectUri: 'https://example.com/callback',
            ipAddress: '127.0.0.1',
            userAgent: 'TestBrowser/1.0',
        );

        $this->assertFalse($result['isNewUser']);
    }

    public function testHandleCallbackThrowsWhenEmailNotVerifiedForLinking(): void
    {
        $providerUser = new ProviderUser(
            providerId: 'google-unverified',
            email: 'unverified@example.com',
            name: 'Unverified User',
            avatar: null,
            emailVerified: false, // Email NOT verified
            rawData: [],
        );

        $this->oauthProvider->method('getAccessToken')->willReturn('access-token');
        $this->oauthProvider->method('getUserInfo')->willReturn($providerUser);

        $this->userRepository->method('findByProvider')->willReturn(null);

        $existingUser = $this->createMockUser('existing-user-2', 'unverified@example.com');
        $this->userRepository->method('findByEmail')->willReturn($existingUser);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageMatches('/Cannot link OAuth account/');

        $this->oauthManager->handleCallback(
            providerName: 'google',
            code: 'auth-code',
            redirectUri: 'https://example.com/callback',
            ipAddress: '127.0.0.1',
            userAgent: 'TestBrowser/1.0',
        );
    }

    // ========================================
    // HANDLE CALLBACK — API TOKEN MODE
    // ========================================

    public function testHandleCallbackReturnsTokensInApiMode(): void
    {
        $config = AuthConfig::forApi('test-secret-key-that-is-at-least-32-characters');
        $tokenManager = $this->createMock(TokenManagerInterface::class);

        $oauthManager = new OAuthManager(
            $this->userRepository,
            $this->sessionService,
            $config,
            $tokenManager,
        );
        $oauthManager->addProvider($this->oauthProvider);

        $providerUser = new ProviderUser(
            providerId: 'google-api',
            email: 'apiuser@example.com',
            name: 'API User',
            avatar: null,
            emailVerified: true,
            rawData: [],
        );

        $this->oauthProvider->method('getAccessToken')->willReturn('access-token');
        $this->oauthProvider->method('getUserInfo')->willReturn($providerUser);

        $user = $this->createMockUser('api-user-1', 'apiuser@example.com');
        $this->userRepository->method('findByProvider')->willReturn($user);

        $tokenManager->method('create')->willReturn([
            'access_token' => 'api-access-token',
            'refresh_token' => 'api-refresh-token',
            'token_type' => 'Bearer',
            'expires_in' => 3600,
        ]);

        $result = $oauthManager->handleCallback(
            providerName: 'google',
            code: 'auth-code',
            redirectUri: 'https://example.com/callback',
            ipAddress: '127.0.0.1',
            userAgent: 'TestBrowser/1.0',
        );

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayNotHasKey('session', $result);
        $this->assertSame('api-access-token', $result['access_token']);
    }

    // ========================================
    // HANDLE CALLBACK — PROVIDER ERRORS
    // ========================================

    public function testHandleCallbackPropagatesProviderError(): void
    {
        $this->oauthProvider
            ->method('getAccessToken')
            ->willThrowException(new \RuntimeException('Provider returned error: invalid_grant'));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Provider returned error: invalid_grant');

        $this->oauthManager->handleCallback(
            providerName: 'google',
            code: 'invalid-code',
            redirectUri: 'https://example.com/callback',
            ipAddress: '127.0.0.1',
            userAgent: 'TestBrowser/1.0',
        );
    }

    public function testHandleCallbackThrowsForUnknownProvider(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->oauthManager->handleCallback(
            providerName: 'unknown',
            code: 'auth-code',
            redirectUri: 'https://example.com/callback',
            ipAddress: '127.0.0.1',
            userAgent: 'TestBrowser/1.0',
        );
    }

    // ========================================
    // HELPERS
    // ========================================

    private function createMockUser(string $id, string $email): User&MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getEmail')->willReturn($email);
        $user->method('getUsername')->willReturn(null);

        return $user;
    }
}
