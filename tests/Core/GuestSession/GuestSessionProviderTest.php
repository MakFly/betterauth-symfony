<?php

declare(strict_types=1);

namespace BetterAuth\Tests\GuestSession;

use BetterAuth\Core\Entities\GuestSession;
use BetterAuth\Core\Entities\User;
use BetterAuth\Core\Interfaces\GuestSessionRepositoryInterface;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use BetterAuth\Providers\GuestSessionProvider\GuestSessionProvider;
use BetterAuth\Tests\Fixtures\TestUser;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class GuestSessionProviderTest extends TestCase
{
    private GuestSessionProvider $provider;
    private GuestSessionRepositoryInterface $guestRepo;
    private UserRepositoryInterface $userRepo;

    protected function setUp(): void
    {
        $this->guestRepo = $this->createMock(GuestSessionRepositoryInterface::class);
        $this->userRepo = $this->createMock(UserRepositoryInterface::class);
        $this->provider = new GuestSessionProvider($this->guestRepo, $this->userRepo, 86400);
    }

    public function testCreateGuestSession(): void
    {
        $this->guestRepo->expects($this->once())
            ->method('generateId')
            ->willReturn('guest-123');

        $guestSession = new GuestSession(
            id: 'guest-123',
            token: 'guest-token-123',
            deviceInfo: 'Test Browser',
            ipAddress: '127.0.0.1',
            createdAt: (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            expiresAt: (new DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s'),
        );

        $this->guestRepo->expects($this->once())
            ->method('create')
            ->willReturn($guestSession);

        $result = $this->provider->createGuestSession('127.0.0.1', 'Test Browser');

        $this->assertInstanceOf(GuestSession::class, $result);
        $this->assertEquals('guest-token-123', $result->token);
    }

    public function testCreateGuestSessionWithMetadata(): void
    {
        $metadata = ['source' => 'landing_page', 'campaign' => 'summer_sale'];

        $this->guestRepo->expects($this->once())
            ->method('generateId')
            ->willReturn('guest-456');

        $guestSession = new GuestSession(
            id: 'guest-456',
            token: 'guest-token-456',
            deviceInfo: 'Mobile Safari',
            ipAddress: '192.168.1.1',
            createdAt: (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            expiresAt: (new DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s'),
            metadata: $metadata,
        );

        $this->guestRepo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data) use ($metadata) {
                return $data['metadata'] === $metadata
                    && $data['device_info'] === 'Mobile Safari'
                    && $data['ip_address'] === '192.168.1.1';
            }))
            ->willReturn($guestSession);

        $result = $this->provider->createGuestSession('Mobile Safari', '192.168.1.1', $metadata);

        $this->assertInstanceOf(GuestSession::class, $result);
        $this->assertEquals($metadata, $result->metadata);
    }

    public function testConvertToUser(): void
    {
        $guestToken = 'valid-guest-token';
        $userData = [
            'email' => 'newuser@example.com',
            'username' => 'New User',
            'password' => 'hashed_password',
        ];

        $guestSession = new GuestSession(
            id: 'guest-789',
            token: $guestToken,
            deviceInfo: null,
            ipAddress: null,
            createdAt: (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            expiresAt: (new DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s'),
        );

        $this->guestRepo->expects($this->once())
            ->method('findByToken')
            ->with($guestToken)
            ->willReturn($guestSession);

        $this->userRepo->expects($this->once())
            ->method('generateId')
            ->willReturn('user-new-123');

        $createdUser = TestUser::fromArray([
            'id' => 'user-new-123',
            'email' => 'newuser@example.com',
            'username' => 'New User',
            'password' => 'hashed_password',
        ]);

        $this->userRepo->expects($this->once())
            ->method('create')
            ->willReturn($createdUser);

        $this->guestRepo->expects($this->once())
            ->method('delete')
            ->with('guest-789')
            ->willReturn(true);

        $result = $this->provider->convertToUser($guestToken, $userData);

        $this->assertInstanceOf(User::class, $result);
        $this->assertEquals('newuser@example.com', $result->getEmail());
    }

    public function testConvertToUserThrowsExceptionForNonExistentSession(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Guest session not found');

        $this->guestRepo->expects($this->once())
            ->method('findByToken')
            ->with('non-existent-token')
            ->willReturn(null);

        $this->provider->convertToUser('non-existent-token', ['email' => 'test@example.com']);
    }

    public function testConvertToUserThrowsExceptionForExpiredSession(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Guest session has expired');

        $expiredSession = new GuestSession(
            id: 'guest-expired',
            token: 'expired-token',
            deviceInfo: null,
            ipAddress: null,
            createdAt: (new DateTimeImmutable('-2 days'))->format('Y-m-d H:i:s'),
            expiresAt: (new DateTimeImmutable('-1 day'))->format('Y-m-d H:i:s'),
        );

        $this->guestRepo->expects($this->once())
            ->method('findByToken')
            ->with('expired-token')
            ->willReturn($expiredSession);

        $this->provider->convertToUser('expired-token', ['email' => 'test@example.com']);
    }

    public function testGetGuestSession(): void
    {
        $token = 'valid-token';
        $guestSession = new GuestSession(
            id: 'guest-get-123',
            token: $token,
            deviceInfo: 'Chrome',
            ipAddress: '10.0.0.1',
            createdAt: (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            expiresAt: (new DateTimeImmutable('+1 day'))->format('Y-m-d H:i:s'),
        );

        $this->guestRepo->expects($this->once())
            ->method('findByToken')
            ->with($token)
            ->willReturn($guestSession);

        $result = $this->provider->getGuestSession($token);

        $this->assertInstanceOf(GuestSession::class, $result);
        $this->assertEquals('guest-get-123', $result->id);
        $this->assertEquals($token, $result->token);
    }

    public function testGetGuestSessionReturnsNullForNonExistent(): void
    {
        $this->guestRepo->expects($this->once())
            ->method('findByToken')
            ->with('non-existent')
            ->willReturn(null);

        $result = $this->provider->getGuestSession('non-existent');

        $this->assertNull($result);
    }

    public function testDeleteGuestSession(): void
    {
        $sessionId = 'guest-to-delete';

        $this->guestRepo->expects($this->once())
            ->method('delete')
            ->with($sessionId)
            ->willReturn(true);

        $result = $this->provider->deleteGuestSession($sessionId);

        $this->assertTrue($result);
    }

    public function testDeleteGuestSessionReturnsFalseForNonExistent(): void
    {
        $this->guestRepo->expects($this->once())
            ->method('delete')
            ->with('non-existent-id')
            ->willReturn(false);

        $result = $this->provider->deleteGuestSession('non-existent-id');

        $this->assertFalse($result);
    }

    public function testCleanupExpiredSessions(): void
    {
        $this->guestRepo->expects($this->once())
            ->method('deleteExpired')
            ->willReturn(5);

        $result = $this->provider->cleanupExpiredSessions();

        $this->assertEquals(5, $result);
    }

    public function testCleanupExpiredSessionsReturnsZeroWhenNoneExpired(): void
    {
        $this->guestRepo->expects($this->once())
            ->method('deleteExpired')
            ->willReturn(0);

        $result = $this->provider->cleanupExpiredSessions();

        $this->assertEquals(0, $result);
    }

    public function testCustomSessionLifetime(): void
    {
        $customLifetime = 3600; // 1 hour
        $provider = new GuestSessionProvider($this->guestRepo, $this->userRepo, $customLifetime);

        $this->guestRepo->expects($this->once())
            ->method('generateId')
            ->willReturn('guest-custom');

        $this->guestRepo->expects($this->once())
            ->method('create')
            ->with($this->callback(function (array $data) {
                $expiresAt = new DateTimeImmutable($data['expires_at']);
                $createdAt = new DateTimeImmutable($data['created_at']);
                $diff = $expiresAt->getTimestamp() - $createdAt->getTimestamp();

                // Allow 5 seconds tolerance for test execution time
                return $diff >= 3595 && $diff <= 3605;
            }))
            ->willReturn(new GuestSession(
                id: 'guest-custom',
                token: 'custom-token',
                deviceInfo: null,
                ipAddress: null,
                createdAt: (new DateTimeImmutable())->format('Y-m-d H:i:s'),
                expiresAt: (new DateTimeImmutable('+1 hour'))->format('Y-m-d H:i:s'),
            ));

        $provider->createGuestSession();
    }
}
