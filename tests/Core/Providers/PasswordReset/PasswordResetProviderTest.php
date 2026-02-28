<?php

declare(strict_types=1);

namespace BetterAuth\Core\Tests\Providers\PasswordReset;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Config\AuthConfig;
use BetterAuth\Core\Entities\PasswordResetToken;
use BetterAuth\Core\Entities\User;
use BetterAuth\Core\Exceptions\RateLimitException;
use BetterAuth\Core\Interfaces\EmailSenderInterface;
use BetterAuth\Core\Interfaces\PasswordResetStorageInterface;
use BetterAuth\Core\Interfaces\RateLimiterInterface;
use BetterAuth\Core\Interfaces\RefreshTokenRepositoryInterface;
use BetterAuth\Core\Interfaces\TokenSignerInterface;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use BetterAuth\Core\PasswordHasher;
use BetterAuth\Core\TokenAuthManager;
use BetterAuth\Providers\PasswordResetProvider\PasswordResetProvider;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for PasswordResetProvider.
 *
 * Covers:
 * - sendResetEmail() — valid/unknown user, rate limiting, callback URL
 * - verifyResetToken() — valid/expired/used tokens
 * - resetPassword() — valid token, expired token, weak password
 * - Token one-time use enforcement
 * - Rate limit enforcement
 *
 * NOTE: AuthManager and TokenAuthManager are both final, so we cannot mock them.
 * We build a real AuthManager in API mode backed by a real TokenAuthManager
 * whose dependencies (UserRepository, TokenSigner, etc.) are mocked via interfaces.
 */
class PasswordResetProviderTest extends TestCase
{
    private UserRepositoryInterface&MockObject $userRepository;
    private PasswordResetStorageInterface&MockObject $passwordResetStorage;
    private EmailSenderInterface&MockObject $emailSender;
    private TokenSignerInterface&MockObject $tokenSigner;
    private RefreshTokenRepositoryInterface&MockObject $refreshTokenRepository;
    private AuthManager $authManager;
    private TokenAuthManager $tokenAuthManager;
    private RateLimiterInterface&MockObject $rateLimiter;
    private PasswordResetProvider $provider;

    protected function setUp(): void
    {
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->passwordResetStorage = $this->createMock(PasswordResetStorageInterface::class);
        $this->emailSender = $this->createMock(EmailSenderInterface::class);
        $this->tokenSigner = $this->createMock(TokenSignerInterface::class);
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $this->rateLimiter = $this->createMock(RateLimiterInterface::class);

        $config = AuthConfig::forApi('test-secret-key-32chars-minimum-length!');

        // Build real TokenAuthManager (final) with mocked interfaces
        $this->tokenAuthManager = new TokenAuthManager(
            userRepository: $this->userRepository,
            refreshTokenRepository: $this->refreshTokenRepository,
            tokenService: $this->tokenSigner,
            passwordHasher: new PasswordHasher(),
            config: $config,
        );

        // Build real AuthManager (final) in API mode
        $this->authManager = new AuthManager(
            config: $config,
            tokenAuthManager: $this->tokenAuthManager,
        );

        $this->provider = new PasswordResetProvider(
            userRepository: $this->userRepository,
            passwordResetStorage: $this->passwordResetStorage,
            emailSender: $this->emailSender,
            authManager: $this->authManager,
            rateLimiter: $this->rateLimiter,
        );
    }

    // ========================================
    // sendResetEmail() TESTS
    // ========================================

    public function testSendResetEmailReturnsSuccessForUnknownUser(): void
    {
        // Security: Don't reveal if user exists
        $this->rateLimiter->method('tooManyAttempts')->willReturn(false);
        $this->userRepository->method('findByEmail')->willReturn(null);

        $this->emailSender->expects($this->never())->method('sendPasswordReset');

        $result = $this->provider->sendResetEmail('unknown@example.com', 'https://example.com/reset');

        $this->assertTrue($result['success']);
    }

    public function testSendResetEmailSendsEmailForKnownUser(): void
    {
        $this->rateLimiter->method('tooManyAttempts')->willReturn(false);

        $user = $this->createMockUser('user-1', 'user@example.com');
        $this->userRepository->method('findByEmail')->willReturn($user);

        $this->passwordResetStorage->method('deleteByEmail')->willReturn(1);

        $storedToken = $this->createValidToken('token-abc', 'user@example.com');
        $this->passwordResetStorage->method('store')->willReturn($storedToken);

        $this->emailSender
            ->expects($this->once())
            ->method('sendPasswordReset')
            ->with(
                'user@example.com',
                $this->stringContains('token='),
            );

        $result = $this->provider->sendResetEmail('user@example.com', 'https://example.com/reset');

        $this->assertTrue($result['success']);
    }

    public function testSendResetEmailInvalidatesExistingTokens(): void
    {
        $this->rateLimiter->method('tooManyAttempts')->willReturn(false);

        $user = $this->createMockUser('user-1', 'user@example.com');
        $this->userRepository->method('findByEmail')->willReturn($user);

        $this->passwordResetStorage
            ->expects($this->once())
            ->method('deleteByEmail')
            ->with('user@example.com')
            ->willReturn(1);

        $storedToken = $this->createValidToken('new-token', 'user@example.com');
        $this->passwordResetStorage->method('store')->willReturn($storedToken);

        $this->emailSender->method('sendPasswordReset')->willReturn(true);

        $this->provider->sendResetEmail('user@example.com', 'https://example.com/reset');
    }

    public function testSendResetEmailAppendsTokenToCallbackUrl(): void
    {
        $this->rateLimiter->method('tooManyAttempts')->willReturn(false);

        $user = $this->createMockUser('user-1', 'user@example.com');
        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->passwordResetStorage->method('deleteByEmail')->willReturn(0);

        $storedToken = $this->createValidToken('reset-token-123', 'user@example.com');
        $this->passwordResetStorage->method('store')->willReturn($storedToken);

        $capturedLink = '';
        $this->emailSender
            ->method('sendPasswordReset')
            ->willReturnCallback(function (string $to, string $link) use (&$capturedLink): bool {
                $capturedLink = $link;

                return true;
            });

        $this->provider->sendResetEmail('user@example.com', 'https://example.com/reset');

        $this->assertStringContainsString('?token=', $capturedLink);
        $this->assertStringStartsWith('https://example.com/reset', $capturedLink);
    }

    public function testSendResetEmailAppendTokenWithAmpersandWhenCallbackHasQuery(): void
    {
        $this->rateLimiter->method('tooManyAttempts')->willReturn(false);

        $user = $this->createMockUser('user-1', 'user@example.com');
        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->passwordResetStorage->method('deleteByEmail')->willReturn(0);

        $storedToken = $this->createValidToken('token-xyz', 'user@example.com');
        $this->passwordResetStorage->method('store')->willReturn($storedToken);

        $capturedLink = '';
        $this->emailSender
            ->method('sendPasswordReset')
            ->willReturnCallback(function (string $to, string $link) use (&$capturedLink): bool {
                $capturedLink = $link;

                return true;
            });

        $this->provider->sendResetEmail('user@example.com', 'https://example.com/reset?lang=fr');

        $this->assertStringContainsString('&token=', $capturedLink);
    }

    public function testSendResetEmailWithNullCallbackUrlDoesNotSendEmail(): void
    {
        $this->rateLimiter->method('tooManyAttempts')->willReturn(false);

        $user = $this->createMockUser('user-1', 'user@example.com');
        $this->userRepository->method('findByEmail')->willReturn($user);
        $this->passwordResetStorage->method('deleteByEmail')->willReturn(0);

        $storedToken = $this->createValidToken('token-no-url', 'user@example.com');
        $this->passwordResetStorage->method('store')->willReturn($storedToken);

        $this->emailSender->expects($this->never())->method('sendPasswordReset');

        $result = $this->provider->sendResetEmail('user@example.com', null);

        $this->assertTrue($result['success']);
    }

    // ========================================
    // RATE LIMITING TESTS
    // ========================================

    public function testSendResetEmailThrowsRateLimitException(): void
    {
        $this->rateLimiter->method('tooManyAttempts')->willReturn(true);
        $this->rateLimiter->method('availableIn')->willReturn(3500);

        $this->expectException(RateLimitException::class);

        $this->provider->sendResetEmail('user@example.com', 'https://example.com/reset');
    }

    public function testSendResetEmailHitsRateLimiterForUnknownUser(): void
    {
        $this->rateLimiter->method('tooManyAttempts')->willReturn(false);
        $this->userRepository->method('findByEmail')->willReturn(null);

        // Rate limiter should be hit even for unknown users
        $this->rateLimiter
            ->expects($this->once())
            ->method('hit')
            ->with('password_reset:ghost@example.com', 3600);

        $this->provider->sendResetEmail('ghost@example.com', 'https://example.com/reset');
    }

    // ========================================
    // verifyResetToken() TESTS
    // ========================================

    public function testVerifyResetTokenReturnsTrueForValidToken(): void
    {
        $token = $this->createValidToken('valid-token', 'user@example.com');
        $this->passwordResetStorage->method('findByToken')->willReturn($token);

        $result = $this->provider->verifyResetToken('valid-token');

        $this->assertTrue($result['valid']);
        $this->assertSame('user@example.com', $result['email']);
    }

    public function testVerifyResetTokenReturnsFalseForNonExistentToken(): void
    {
        $this->passwordResetStorage->method('findByToken')->willReturn(null);

        $result = $this->provider->verifyResetToken('non-existent-token');

        $this->assertFalse($result['valid']);
        $this->assertArrayNotHasKey('email', $result);
    }

    public function testVerifyResetTokenReturnsFalseForExpiredToken(): void
    {
        $expiredToken = $this->createExpiredToken('expired-token', 'user@example.com');
        $this->passwordResetStorage->method('findByToken')->willReturn($expiredToken);

        $result = $this->provider->verifyResetToken('expired-token');

        $this->assertFalse($result['valid']);
    }

    public function testVerifyResetTokenReturnsFalseForUsedToken(): void
    {
        $usedToken = $this->createUsedToken('used-token', 'user@example.com');
        $this->passwordResetStorage->method('findByToken')->willReturn($usedToken);

        $result = $this->provider->verifyResetToken('used-token');

        $this->assertFalse($result['valid']);
    }

    // ========================================
    // resetPassword() TESTS
    // ========================================

    public function testResetPasswordSuccessfully(): void
    {
        $token = $this->createValidToken('valid-token', 'user@example.com');
        $this->passwordResetStorage->method('findByToken')->willReturn($token);

        $user = $this->createMockUser('user-1', 'user@example.com');
        $this->userRepository->method('findByEmail')->willReturn($user);

        // Setup for real TokenAuthManager.updatePassword flow
        $this->userRepository->method('findById')->willReturn($user);
        $this->userRepository->method('update')->willReturn($user);
        $this->refreshTokenRepository->method('revokeAllForUser')->willReturn(0);

        $this->passwordResetStorage
            ->expects($this->once())
            ->method('markAsUsed')
            ->with('valid-token');

        $this->passwordResetStorage
            ->expects($this->once())
            ->method('deleteByEmail')
            ->with('user@example.com');

        $this->rateLimiter
            ->expects($this->once())
            ->method('clear')
            ->with('password_reset:user@example.com');

        $result = $this->provider->resetPassword('valid-token', 'newSecurePassword123!');

        $this->assertTrue($result['success']);
    }

    public function testResetPasswordFailsWithShortPassword(): void
    {
        $result = $this->provider->resetPassword('any-token', 'short');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('8 characters', $result['error']);
    }

    public function testResetPasswordFailsWithExactly7Characters(): void
    {
        $result = $this->provider->resetPassword('any-token', '1234567');

        $this->assertFalse($result['success']);
    }

    public function testResetPasswordSucceedsWithExactly8Characters(): void
    {
        $token = $this->createValidToken('valid-token', 'user@example.com');
        $this->passwordResetStorage->method('findByToken')->willReturn($token);

        $user = $this->createMockUser('user-1', 'user@example.com');
        $this->userRepository->method('findByEmail')->willReturn($user);

        $this->userRepository->method('findById')->willReturn($user);
        $this->userRepository->method('update')->willReturn($user);
        $this->refreshTokenRepository->method('revokeAllForUser')->willReturn(0);
        $this->passwordResetStorage->method('markAsUsed')->willReturn(true);
        $this->passwordResetStorage->method('deleteByEmail')->willReturn(1);
        $this->rateLimiter->method('clear')->willReturn(true);

        $result = $this->provider->resetPassword('valid-token', '12345678');

        $this->assertTrue($result['success']);
    }

    public function testResetPasswordFailsForExpiredToken(): void
    {
        $expiredToken = $this->createExpiredToken('expired-token', 'user@example.com');
        $this->passwordResetStorage->method('findByToken')->willReturn($expiredToken);

        $result = $this->provider->resetPassword('expired-token', 'newPassword123!');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid or expired', $result['error']);
    }

    public function testResetPasswordFailsForUsedToken(): void
    {
        $usedToken = $this->createUsedToken('used-token', 'user@example.com');
        $this->passwordResetStorage->method('findByToken')->willReturn($usedToken);

        $result = $this->provider->resetPassword('used-token', 'newPassword123!');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Invalid or expired', $result['error']);
    }

    public function testResetPasswordFailsWhenUserNotFound(): void
    {
        $token = $this->createValidToken('valid-token', 'missing@example.com');
        $this->passwordResetStorage->method('findByToken')->willReturn($token);

        $this->userRepository->method('findByEmail')->willReturn(null);

        $result = $this->provider->resetPassword('valid-token', 'newPassword123!');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('User not found', $result['error']);
    }

    /**
     * Verifies that token is one-time use: markAsUsed is called after successful reset.
     */
    public function testResetPasswordMarksTokenAsUsed(): void
    {
        $token = $this->createValidToken('one-time-token', 'user@example.com');
        $this->passwordResetStorage->method('findByToken')->willReturn($token);

        $user = $this->createMockUser('user-1', 'user@example.com');
        $this->userRepository->method('findByEmail')->willReturn($user);

        $this->userRepository->method('findById')->willReturn($user);
        $this->userRepository->method('update')->willReturn($user);
        $this->refreshTokenRepository->method('revokeAllForUser')->willReturn(0);
        $this->passwordResetStorage->method('deleteByEmail')->willReturn(1);
        $this->rateLimiter->method('clear')->willReturn(true);

        $this->passwordResetStorage
            ->expects($this->once())
            ->method('markAsUsed')
            ->with('one-time-token')
            ->willReturn(true);

        $this->provider->resetPassword('one-time-token', 'secureNewPass!');
    }

    // ========================================
    // cancelReset() TESTS
    // ========================================

    public function testCancelResetDeletesToken(): void
    {
        $this->passwordResetStorage
            ->expects($this->once())
            ->method('delete')
            ->with('cancel-token')
            ->willReturn(true);

        $result = $this->provider->cancelReset('cancel-token');

        $this->assertTrue($result);
    }

    public function testCancelResetReturnsFalseForNonExistentToken(): void
    {
        $this->passwordResetStorage->method('delete')->willReturn(false);

        $result = $this->provider->cancelReset('non-existent');

        $this->assertFalse($result);
    }

    // ========================================
    // requestReset() DEPRECATED ALIAS TESTS
    // ========================================

    public function testRequestResetReturnsTrueOnSuccess(): void
    {
        $this->rateLimiter->method('tooManyAttempts')->willReturn(false);
        $this->userRepository->method('findByEmail')->willReturn(null);

        $result = $this->provider->requestReset('user@example.com', 'https://example.com/reset');

        $this->assertTrue($result);
    }

    // ========================================
    // HELPERS
    // ========================================

    private function createMockUser(string $id, string $email): User&MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getEmail')->willReturn($email);

        return $user;
    }

    private function createValidToken(string $tokenValue, string $email): PasswordResetToken
    {
        return PasswordResetToken::fromArray([
            'token' => $tokenValue,
            'email' => $email,
            'expires_at' => (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s'),
            'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'used' => false,
        ]);
    }

    private function createExpiredToken(string $tokenValue, string $email): PasswordResetToken
    {
        return PasswordResetToken::fromArray([
            'token' => $tokenValue,
            'email' => $email,
            'expires_at' => (new DateTimeImmutable('-1 hour'))->format('Y-m-d H:i:s'),
            'created_at' => (new DateTimeImmutable('-2 hours'))->format('Y-m-d H:i:s'),
            'used' => false,
        ]);
    }

    private function createUsedToken(string $tokenValue, string $email): PasswordResetToken
    {
        return PasswordResetToken::fromArray([
            'token' => $tokenValue,
            'email' => $email,
            'expires_at' => (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s'),
            'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'used' => true,
        ]);
    }
}
