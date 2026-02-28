<?php

declare(strict_types=1);

namespace BetterAuth\Core\Tests\Core;

use BetterAuth\Core\Config\AuthConfig;
use BetterAuth\Core\Entities\RefreshToken;
use BetterAuth\Core\Entities\SimpleUser;
use BetterAuth\Core\Interfaces\RefreshTokenRepositoryInterface;
use BetterAuth\Core\Interfaces\TokenSignerInterface;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use BetterAuth\Core\PasswordHasher;
use BetterAuth\Core\TokenAuthManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class TokenAuthManagerTest extends TestCase
{
    private TokenSignerInterface&MockObject $tokenSigner;
    private UserRepositoryInterface&MockObject $userRepository;
    private RefreshTokenRepositoryInterface&MockObject $refreshTokenRepository;
    private PasswordHasher $passwordHasher;
    private AuthConfig $config;
    private TokenAuthManager $tokenAuthManager;

    protected function setUp(): void
    {
        $this->tokenSigner = $this->createMock(TokenSignerInterface::class);
        $this->userRepository = $this->createMock(UserRepositoryInterface::class);
        $this->refreshTokenRepository = $this->createMock(RefreshTokenRepositoryInterface::class);
        $this->passwordHasher = new PasswordHasher();
        $this->config = AuthConfig::forApi('test-secret-key-that-is-at-least-32-characters');

        $this->tokenAuthManager = new TokenAuthManager(
            $this->userRepository,
            $this->refreshTokenRepository,
            $this->tokenSigner,
            $this->passwordHasher,
            $this->config,
        );
    }

    public function testSignInExcludesPasswordFromResponse(): void
    {
        $password = 'test-password-123';
        $hashedPassword = $this->passwordHasher->hash($password);

        $user = SimpleUser::fromArray([
            'id' => 'user-123',
            'email' => 'test@example.com',
            'password_hash' => $hashedPassword,
            'username' => 'Test User',
            'email_verified' => true,
            'created_at' => '2024-01-01 00:00:00',
            'updated_at' => '2024-01-01 00:00:00',
        ]);

        $this->userRepository
            ->expects($this->once())
            ->method('findByEmail')
            ->with('test@example.com')
            ->willReturn($user);

        $this->tokenSigner
            ->expects($this->once())
            ->method('sign')
            ->willReturn('access-token-value');

        $refreshToken = $this->createMock(RefreshToken::class);
        $refreshToken->method('getToken')->willReturn('refresh-token-value');

        $this->refreshTokenRepository
            ->expects($this->once())
            ->method('create')
            ->willReturn($refreshToken);

        $result = $this->tokenAuthManager->signIn('test@example.com', $password);

        $this->assertArrayHasKey('user', $result);
        $this->assertIsArray($result['user'], 'User should be an array (DTO), not an object');
        $this->assertArrayNotHasKey('password', $result['user'], 'Password must be excluded from login response');
        $this->assertArrayHasKey('id', $result['user']);
        $this->assertArrayHasKey('email', $result['user']);
        $this->assertArrayHasKey('username', $result['user']);
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
    }
}

