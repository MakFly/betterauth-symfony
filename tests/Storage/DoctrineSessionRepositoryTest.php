<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Storage;

use BetterAuth\Core\Entities\Session;
use BetterAuth\Symfony\Tests\Fixtures\TestSession;
use BetterAuth\Symfony\Service\UserIdConverter;
use BetterAuth\Symfony\Storage\Doctrine\DoctrineSessionRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DoctrineSessionRepository.
 */
class DoctrineSessionRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserIdConverter $idConverter;
    private EntityRepository&MockObject $entityRepository;
    private DoctrineSessionRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityRepository = $this->createMock(EntityRepository::class);

        $this->entityManager->method('getRepository')
            ->willReturn($this->entityRepository);

        $this->idConverter = $this->buildStringIdConverter();

        $this->repository = new DoctrineSessionRepository(
            $this->entityManager,
            $this->idConverter,
            TestSession::class,
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

    private function makeDoctrineSession(
        string $token = 'tok-abc',
        string $userId = 'user-1',
    ): object {
        return new class($token, $userId) {
            public function __construct(
                private string $token,
                private string $userId,
            ) {}

            public function getToken(): string { return $this->token; }
            public function setToken(string $t): void { $this->token = $t; }
            public function getUserId(): string { return $this->userId; }
            public function setUserId(string|int $id): void { $this->userId = (string) $id; }
            public function getExpiresAt(): \DateTimeImmutable { return new \DateTimeImmutable('+1 hour'); }
            public function setExpiresAt(\DateTimeImmutable $d): void {}
            public function getIpAddress(): string { return '127.0.0.1'; }
            public function setIpAddress(string $ip): void {}
            public function getUserAgent(): string { return 'PHPUnit'; }
            public function setUserAgent(string $ua): void {}
            public function getMetadata(): ?array { return null; }
            public function setMetadata(?array $m): void {}
            public function getActiveOrganizationId(): ?string { return null; }
            public function setActiveOrganizationId(?string $id): void {}
            public function getActiveTeamId(): ?string { return null; }
            public function setActiveTeamId(?string $id): void {}
            public function getCreatedAt(): \DateTimeImmutable { return new \DateTimeImmutable(); }
            public function getUpdatedAt(): \DateTimeImmutable { return new \DateTimeImmutable(); }
            public function setUpdatedAt(\DateTimeImmutable $d): void {}
        };
    }

    // --- findByToken ---

    public function testFindByTokenReturnsSessionWhenFound(): void
    {
        $doctrineSession = $this->makeDoctrineSession('tok-123', 'user-1');

        $this->entityRepository->method('find')->with('tok-123')->willReturn($doctrineSession);

        $session = $this->repository->findByToken('tok-123');

        $this->assertInstanceOf(Session::class, $session);
        $this->assertSame('tok-123', $session->getToken());
    }

    public function testFindByTokenReturnsNullWhenNotFound(): void
    {
        $this->entityRepository->method('find')->willReturn(null);

        $this->assertNull($this->repository->findByToken('missing'));
    }

    // --- findByUserId ---

    public function testFindByUserIdReturnsSessions(): void
    {
        $ds1 = $this->makeDoctrineSession('tok-1', 'user-42');
        $ds2 = $this->makeDoctrineSession('tok-2', 'user-42');

        $this->entityRepository->method('findBy')
            ->with(['userId' => 'user-42'])
            ->willReturn([$ds1, $ds2]);

        $sessions = $this->repository->findByUserId('user-42');

        $this->assertCount(2, $sessions);
        $this->assertInstanceOf(Session::class, $sessions[0]);
    }

    public function testFindByUserIdReturnsEmptyArrayWhenNone(): void
    {
        $this->entityRepository->method('findBy')->willReturn([]);

        $this->assertSame([], $this->repository->findByUserId('user-empty'));
    }

    // --- create ---

    public function testCreatePersistsAndReturnsSession(): void
    {
        $this->entityManager->expects($this->once())->method('persist');
        $this->entityManager->expects($this->once())->method('flush');

        $session = $this->repository->create([
            'token' => 'tok-new',
            'user_id' => 'user-1',
            'expires_at' => '+1 hour',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
        ]);

        $this->assertInstanceOf(Session::class, $session);
        $this->assertSame('tok-new', $session->getToken());
    }

    // --- update ---

    public function testUpdateThrowsWhenSessionNotFound(): void
    {
        $this->entityRepository->method('find')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Session not found: ghost-tok');

        $this->repository->update('ghost-tok', ['metadata' => []]);
    }

    public function testUpdateModifiesAndReturnsSession(): void
    {
        $doctrineSession = $this->makeDoctrineSession('tok-abc', 'user-1');

        $this->entityRepository->method('find')->with('tok-abc')->willReturn($doctrineSession);
        $this->entityManager->expects($this->once())->method('flush');

        $session = $this->repository->update('tok-abc', ['metadata' => ['key' => 'value']]);

        $this->assertInstanceOf(Session::class, $session);
    }

    // --- delete ---

    public function testDeleteReturnsTrueWhenFound(): void
    {
        $doctrineSession = $this->makeDoctrineSession('tok-1');

        $this->entityRepository->method('find')->with('tok-1')->willReturn($doctrineSession);
        $this->entityManager->expects($this->once())->method('remove')->with($doctrineSession);
        $this->entityManager->expects($this->once())->method('flush');

        $this->assertTrue($this->repository->delete('tok-1'));
    }

    public function testDeleteReturnsFalseWhenNotFound(): void
    {
        $this->entityRepository->method('find')->willReturn(null);

        $this->assertFalse($this->repository->delete('nonexistent'));
    }

    // --- deleteByUserId ---

    public function testDeleteByUserIdExecutesQueryAndReturnsCount(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(Query::class)->disableOriginalConstructor()->getMock();

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);
        $qb->method('delete')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $query->method('execute')->willReturn(3);

        $count = $this->repository->deleteByUserId('user-1');

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
        $query->method('execute')->willReturn(8);

        $this->assertSame(8, $this->repository->deleteExpired());
    }
}
