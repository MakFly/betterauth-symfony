<?php

declare(strict_types=1);

namespace BetterAuth\Tests\AccountLinking;

use BetterAuth\Core\Entities\AccountLink;
use BetterAuth\Core\Interfaces\AccountLinkRepositoryInterface;
use BetterAuth\Providers\AccountLinkProvider\AccountLinkProvider;
use BetterAuth\Tests\Fixtures\TestUser;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

class AccountLinkProviderTest extends TestCase
{
    private AccountLinkProvider $provider;
    private AccountLinkRepositoryInterface $repository;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(AccountLinkRepositoryInterface::class);
        $this->provider = new AccountLinkProvider($this->repository);
    }

    public function testLinkAccount(): void
    {
        $user = TestUser::fromArray([
            'id' => 'user-123',
            'email' => 'user@example.com',
            'password' => null,
            'username' => 'Test User',
            'avatar' => null,
            'email_verified' => true,
            'email_verified_at' => null,
            'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $this->repository->expects($this->once())
            ->method('isLinked')
            ->with('user-123', 'google')
            ->willReturn(false);

        $this->repository->expects($this->once())
            ->method('findByProvider')
            ->with('google', 'google-123')
            ->willReturn(null);

        $this->repository->expects($this->once())
            ->method('countForUser')
            ->with('user-123')
            ->willReturn(0);

        $this->repository->expects($this->once())
            ->method('generateId')
            ->willReturn('link-123');

        $accountLink = new AccountLink(
            id: 'link-123',
            userId: 'user-123',
            provider: 'google',
            providerId: 'google-123',
            providerEmail: 'user@gmail.com',
            isPrimary: true,
            status: 'verified',
            linkedAt: new DateTimeImmutable(),
        );

        $this->repository->expects($this->once())
            ->method('create')
            ->willReturn($accountLink);

        $result = $this->provider->linkAccount($user, 'google', 'google-123', 'user@gmail.com');

        $this->assertInstanceOf(AccountLink::class, $result);
        $this->assertEquals('google', $result->provider);
    }

    public function testCannotLinkAlreadyLinkedProvider(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage("Provider 'google' is already linked to this user");

        $user = TestUser::fromArray([
            'id' => 'user-123',
            'email' => 'user@example.com',
            'password' => null,
            'username' => 'Test User',
            'avatar' => null,
            'email_verified' => true,
            'email_verified_at' => null,
            'created_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
            'updated_at' => (new DateTimeImmutable())->format('Y-m-d H:i:s'),
        ]);

        $this->repository->expects($this->once())
            ->method('isLinked')
            ->with('user-123', 'google')
            ->willReturn(true);

        $this->provider->linkAccount($user, 'google', 'google-123');
    }
}
