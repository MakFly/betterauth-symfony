<?php

declare(strict_types=1);

namespace BetterAuth\Core\Tests\Core;

use BetterAuth\Core\Config\AuthConfig;
use BetterAuth\Core\Entities\RefreshToken;
use BetterAuth\Core\Entities\User;
use BetterAuth\Core\Interfaces\RefreshTokenRepositoryInterface;
use BetterAuth\Core\Interfaces\TokenSignerInterface;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use BetterAuth\Core\TokenManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Tests for TokenManager - High-level token management like Lexik's JWTManager.
 */
class TokenManagerTest extends TestCase
{
    private TokenSignerInterface&MockObject $tokenSigner;
    private UserRepositoryInterface&MockObject $userRepository;
    private RefreshTokenRepositoryInterface&MockObject $refreshTokenRepository;
    private AuthConfig $config;
    private TokenManager $tokenManager;

    protected function setUp(): void
    {
        $this->tokenSigner = $this->createMock(TokenSignerInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $this->config = AuthConfig::forApi('test-secret-key-that-is-at-least-32-characters');

        $this->tokenManager = new TokenManager(
            $this->tokenSigner,
            $this->userRepository,
            $this->refreshTokenRepository,
            $this->config,
        );
    }

    public function testCreate(): void
    {
        $user = $this->createMockUser('user-123', 'test@example.com', 'Test User');

        $this->tokenSigner
            ->expects($this->once())
            ->method('sign')
            ->with(
                $this->callback(function (array $payload) {
                    return $payload['sub'] === 'user-123'
                        && $payload['type'] === 'access'
                        && $payload['data']['email'] === 'test@example.com'
                        && $payload['data']['username'] === 'Test User';
                }),
                $this->config->tokenLifetime,
            )
            ->willReturn('access-token-value');

        $refreshToken = $this->createMock(RefreshToken::class);
        $refreshToken->method('getToken')->willReturn('refresh-token-value');

        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('create')
            ->willReturn($refreshToken);

        $result = $this->tokenManager->create($user);

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('token_type', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertSame('access-token-value', $result['access_token']);
        $this->assertSame('refresh-token-value', $result['refresh_token']);
        $this->assertSame('Bearer', $result['token_type']);
        $this->assertSame($this->config->tokenLifetime, $result['expires_in']);
    }

    public function testCreateAccessToken(): void
    {
        $user = $this->createMockUser('user-456', 'user@example.com', 'Another User');

        $this->tokenSigner
            ->expects($this->once())
            ->method('sign')
            ->willReturn('just-access-token');

        $accessToken = $this->tokenManager->createAccessToken($user);

        $this->assertSame('just-access-token', $accessToken);
    }

    public function testParse(): void
    {
        $expectedPayload = [
            'sub' => 'user-123',
            'type' => 'access',
            'data' => ['email' => 'test@example.com'],
            'exp' => time() + 3600,
            'iat' => time(),
        ];

        $this->tokenSigner
            ->expects($this->once())
            ->method('verify')
            ->with('some-token')
            ->willReturn($expectedPayload);

        $result = $this->tokenManager->parse('some-token');

        $this->assertSame($expectedPayload, $result);
    }

    public function testParseInvalidToken(): void
    {
        $this->tokenSigner
            ->expects($this->once())
            ->method('verify')
            ->with('invalid-token')
            ->willReturn(null);

        $result = $this->tokenManager->parse('invalid-token');

        $this->assertNull($result);
    }

    public function testDecode(): void
    {
        $expectedPayload = [
            'sub' => 'user-123',
            'type' => 'access',
        ];

        $this->tokenSigner
            ->expects($this->once())
            ->method('decode')
            ->with('some-token')
            ->willReturn($expectedPayload);

        $result = $this->tokenManager->decode('some-token');

        $this->assertSame($expectedPayload, $result);
    }

    public function testGetUserFromToken(): void
    {
        $user = $this->createMockUser('user-789', 'found@example.com', 'Found User');

        $this->tokenSigner
            ->expects($this->once())
            ->method('verify')
            ->with('valid-token')
            ->willReturn(['sub' => 'user-789', 'type' => 'access']);

        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->with('user-789')
            ->willReturn($user);

        $result = $this->tokenManager->getUserFromToken('valid-token');

        $this->assertSame($user, $result);
    }

    public function testGetUserFromTokenInvalidToken(): void
    {
        $this->tokenSigner
            ->expects($this->once())
            ->method('verify')
            ->with('invalid-token')
            ->willReturn(null);

        $this->userRepository
            ->expects($this->never())
            ->method('findById');

        $result = $this->tokenManager->getUserFromToken('invalid-token');

        $this->assertNull($result);
    }

    public function testGetUserFromTokenUserNotFound(): void
    {
        $this->tokenSigner
            ->expects($this->once())
            ->method('verify')
            ->with('valid-token')
            ->willReturn(['sub' => 'non-existent-user', 'type' => 'access']);

        $this->userRepository
            ->expects($this->once())
            ->method('findById')
            ->with('non-existent-user')
            ->willReturn(null);

        $result = $this->tokenManager->getUserFromToken('valid-token');

        $this->assertNull($result);
    }

    public function testGetUserIdClaim(): void
    {
        $this->assertSame('sub', $this->tokenManager->getUserIdClaim());
    }

    private function createMockUser(string $id, string $email, ?string $username): User&MockObject
    {
        $user = $this->createMock(User::class);
        $user->method('getId')->willReturn($id);
        $user->method('getEmail')->willReturn($email);
        $user->method('getUsername')->willReturn($username);

        return $user;
    }
}
