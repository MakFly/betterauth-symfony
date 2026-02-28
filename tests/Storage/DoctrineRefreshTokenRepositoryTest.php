<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Storage;

use BetterAuth\Core\Entities\RefreshToken;
use BetterAuth\Symfony\Tests\Fixtures\TestRefreshToken;
use BetterAuth\Symfony\Service\UserIdConverter;
use BetterAuth\Symfony\Storage\Doctrine\DoctrineRefreshTokenRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DoctrineRefreshTokenRepository.
 */
class DoctrineRefreshTokenRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserIdConverter $idConverter;
    private EntityRepository&MockObject $entityRepository;
    private DoctrineRefreshTokenRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityRepository = $this->createMock(EntityRepository::class);

        $this->entityManager->method('getRepository')
            ->willReturn($this->entityRepository);

        $this->idConverter = $this->buildStringIdConverter();

        $this->repository = new DoctrineRefreshTokenRepository(
            $this->entityManager,
            $this->idConverter,
            TestRefreshToken::class,
        );
    }

    private function buildStringIdConverter(): UserIdConverter
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $metadata = $this->createMock(ClassMetadata::class);

        $em->method('getClassMetadata')->willReturn($metadata);
        $metadata->method('getIdentifierFieldNames')->willReturn(['id']);
        $metadata->method('getTypeOfField')->with('id')->willReturn('string');

        return new UserIdConverter($em, 'App\\Entity\\User');
    }

    private function makeDoctrineToken(
        string $token = 'hashed-token',
        string $userId = 'user-1',
        bool $revoked = false,
    ): object {
        return new class($token, $userId, $revoked) {
            public function __construct(
                private string $token,
                private string $userId,
                private bool $revoked,
            ) {}

            public function getToken(): string { return $this->token; }
            public function setToken(string $t): void { $this->token = $t; }
            public function getUserId(): string { return $this->userId; }
            public function setUserId(string|int $id): void { $this->userId = (string) $id; }
            public function getExpiresAt(): \DateTimeImmutable { return new \DateTimeImmutable('+30 days'); }
            public function setExpiresAt(\DateTimeImmutable $d): void {}
            public function getCreatedAt(): \DateTimeImmutable { return new \DateTimeImmutable(); }
            public function isRevoked(): bool { return $this->revoked; }
            public function setRevoked(bool $r): void { $this->revoked = $r; }
            public function getReplacedBy(): ?string { return null; }
            public function setReplacedBy(?string $s): void {}
        };
    }

    // --- findByToken ---

    public function testFindByTokenReturnsTokenWhenFound(): void
    {
        $rawToken = 'my-secret-token';
        $hashedToken = hash('sha256', $rawToken);
        $doctrineToken = $this->makeDoctrineToken($hashedToken);

        $this->entityRepository->method('find')->with($hashedToken)->willReturn($doctrineToken);

        $token = $this->repository->findByToken($rawToken);

        $this->assertInstanceOf(RefreshToken::class, $token);
    }

    public function testFindByTokenReturnsNullWhenNotFound(): void
    {
        $this->entityRepository->method('find')->willReturn(null);

        $this->assertNull($this->repository->findByToken('missing-token'));
    }

    // --- findByUserId ---

    public function testFindByUserIdReturnsTokens(): void
    {
        $dt1 = $this->makeDoctrineToken('hash-1', 'user-42');
        $dt2 = $this->makeDoctrineToken('hash-2', 'user-42');

        $this->entityRepository->method('findBy')
            ->with(['userId' => 'user-42'])
            ->willReturn([$dt1, $dt2]);

        $tokens = $this->repository->findByUserId('user-42');

        $this->assertCount(2, $tokens);
        $this->assertInstanceOf(RefreshToken::class, $tokens[0]);
    }

    public function testFindByUserIdReturnsEmptyArrayWhenNone(): void
    {
        $this->entityRepository->method('findBy')->willReturn([]);

        $this->assertSame([], $this->repository->findByUserId('user-empty'));
    }

    // --- create ---

    public function testCreatePersistsAndReturnsRawToken(): void
    {
        $rawToken = 'raw-refresh-token';
        $hashedToken = hash('sha256', $rawToken);
        $doctrineToken = $this->makeDoctrineToken($hashedToken);

        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        // After flush, findByToken is called internally
        $this->entityRepository->method('find')->with($hashedToken)->willReturn($doctrineToken);

        $token = $this->repository->create([
            'token' => $rawToken,
            'userId' => 'user-1',
            'expiresAt' => '+30 days',
        ]);

        // The raw token must be returned, not the stored hash
        $this->assertInstanceOf(RefreshToken::class, $token);
        $this->assertSame($rawToken, $token->getToken());
    }

    // --- revoke ---

    public function testRevokeReturnsTrueWhenFound(): void
    {
        $rawToken = 'revoke-me';
        $hashedToken = hash('sha256', $rawToken);
        $doctrineToken = $this->makeDoctrineToken($hashedToken);

        $this->entityRepository->method('find')->with($hashedToken)->willReturn($doctrineToken);
        $this->entityManager->expects($this->once())->method('flush');

        $this->assertTrue($this->repository->revoke($rawToken));
    }

    public function testRevokeReturnsFalseWhenNotFound(): void
    {
        $this->entityRepository->method('find')->willReturn(null);

        $this->assertFalse($this->repository->revoke('unknown-token'));
    }

    public function testRevokeWithReplacedByStoresValue(): void
    {
        $rawToken = 'old-token';
        $doctrineToken = $this->makeDoctrineToken(hash('sha256', $rawToken));

        $this->entityRepository->method('find')->willReturn($doctrineToken);
        $this->entityManager->expects($this->once())->method('flush');

        $this->assertTrue($this->repository->revoke($rawToken, 'new-token'));
    }

    // --- revokeAllForUser ---

    public function testRevokeAllForUserExecutesQueryAndReturnsCount(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);
        $qb->method('update')->willReturnSelf();
        $qb->method('set')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $query->method('execute')->willReturn(3);

        $count = $this->repository->revokeAllForUser('user-1');

        $this->assertSame(3, $count);
    }

    // --- deleteExpired ---

    public function testDeleteExpiredExecutesQueryAndReturnsCount(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);
        $qb->method('delete')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $query->method('execute')->willReturn(5);

        $this->assertSame(5, $this->repository->deleteExpired());
    }

    // --- consume ---

    public function testConsumeReturnsNullWhenNothingUpdated(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);
        $qb->method('update')->willReturnSelf();
        $qb->method('set')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $query->method('execute')->willReturn(0);

        $this->assertNull($this->repository->consume('already-revoked-token'));
    }

    public function testConsumeReturnsTokenWhenConsumed(): void
    {
        $rawToken = 'consume-me';
        $hashedToken = hash('sha256', $rawToken);
        $doctrineToken = $this->makeDoctrineToken($hashedToken);

        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();

        $this->entityManager->expects($this->once())
            ->method('createQueryBuilder')
            ->willReturn($qb);

        $qb->method('update')->willReturnSelf();
        $qb->method('set')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('andWhere')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $query->method('execute')->willReturn(1);

        $this->entityRepository->method('find')->with($hashedToken)->willReturn($doctrineToken);

        $token = $this->repository->consume($rawToken);

        $this->assertInstanceOf(RefreshToken::class, $token);
    }
}
