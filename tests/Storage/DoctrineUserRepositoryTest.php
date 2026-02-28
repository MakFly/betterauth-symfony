<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Storage;

use BetterAuth\Core\Entities\User;
use BetterAuth\Symfony\Service\UserIdConverter;
use BetterAuth\Symfony\Storage\Doctrine\DoctrineUserRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for DoctrineUserRepository.
 */
class DoctrineUserRepositoryTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private UserIdConverter $idConverter;
    private EntityRepository&MockObject $entityRepository;
    private DoctrineUserRepository $repository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityRepository = $this->createMock(EntityRepository::class);

        $this->entityManager->method('getRepository')
            ->willReturn($this->entityRepository);

        // Build a real UserIdConverter with a mocked EntityManager that returns string ID type
        $this->idConverter = $this->buildStringIdConverter();

        $this->repository = new DoctrineUserRepository(
            $this->entityManager,
            $this->idConverter,
            'App\\Entity\\User',
        );
    }

    /**
     * Create a UserIdConverter configured for string (UUID) IDs.
     */
    private function buildStringIdConverter(): UserIdConverter
    {
        $em = $this->createMock(EntityManagerInterface::class);
        $metadata = $this->createMock(ClassMetadata::class);

        $em->method('getClassMetadata')->willReturn($metadata);
        $metadata->method('getIdentifierFieldNames')->willReturn(['id']);
        $metadata->method('getTypeOfField')->with('id')->willReturn('string');

        return new UserIdConverter($em, 'App\\Entity\\User');
    }

    private function makeDoctrineUser(
        string $id = 'user-uuid-1',
        string $email = 'test@example.com',
    ): object {
        return new class($id, $email) {
            public function __construct(
                private string $id,
                private string $email,
            ) {}

            public function getId(): string { return $this->id; }
            public function getEmail(): string { return $this->email; }
            public function setEmail(string $e): void { $this->email = $e; }
            public function getPassword(): ?string { return null; }
            public function setPassword(?string $p): void {}
            public function getUsername(): ?string { return null; }
            public function setUsername(?string $u): void {}
            public function getAvatar(): ?string { return null; }
            public function setAvatar(?string $a): void {}
            public function getRoles(): array { return ['ROLE_USER']; }
            public function isEmailVerified(): bool { return false; }
            public function setEmailVerified(bool $v): void {}
            public function getEmailVerifiedAt(): ?\DateTimeImmutable { return null; }
            public function setEmailVerifiedAt(?\DateTimeImmutable $d): void {}
            public function getMetadata(): ?array { return null; }
            public function setMetadata(?array $m): void {}
            public function getCreatedAt(): \DateTimeImmutable { return new \DateTimeImmutable(); }
            public function getUpdatedAt(): \DateTimeImmutable { return new \DateTimeImmutable(); }
            public function setUpdatedAt(\DateTimeImmutable $d): void {}
            public function setId(string $id): void { $this->id = $id; }
        };
    }

    // --- findById ---

    public function testFindByIdReturnsUserWhenFound(): void
    {
        $doctrineUser = $this->makeDoctrineUser('uuid-1', 'found@example.com');

        $this->entityRepository->method('find')->with('uuid-1')->willReturn($doctrineUser);

        $user = $this->repository->findById('uuid-1');

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('found@example.com', $user->getEmail());
    }

    public function testFindByIdReturnsNullWhenNotFound(): void
    {
        $this->entityRepository->method('find')->willReturn(null);

        $this->assertNull($this->repository->findById('nonexistent'));
    }

    // --- findByEmail ---

    public function testFindByEmailReturnsUserWhenFound(): void
    {
        $doctrineUser = $this->makeDoctrineUser('uuid-2', 'email@example.com');

        $this->entityRepository->method('findOneBy')
            ->with(['email' => 'email@example.com'])
            ->willReturn($doctrineUser);

        $user = $this->repository->findByEmail('email@example.com');

        $this->assertInstanceOf(User::class, $user);
        $this->assertSame('email@example.com', $user->getEmail());
    }

    public function testFindByEmailReturnsNullWhenNotFound(): void
    {
        $this->entityRepository->method('findOneBy')->willReturn(null);

        $this->assertNull($this->repository->findByEmail('nobody@example.com'));
    }

    // --- findByProvider ---

    public function testFindByProviderThrowsOnInvalidProvider(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid provider name');

        $this->repository->findByProvider('bad provider!', 'id-1');
    }

    public function testFindByProviderReturnsNullWhenNotFound(): void
    {
        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(\Doctrine\ORM\Query::class)->disableOriginalConstructor()->getMock();

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $query->method('getOneOrNullResult')->willReturn(null);

        $this->assertNull($this->repository->findByProvider('google', 'g-123'));
    }

    public function testFindByProviderReturnsUserWhenFound(): void
    {
        $doctrineUser = $this->makeDoctrineUser('uuid-3', 'oauth@example.com');

        $qb = $this->createMock(QueryBuilder::class);
        $query = $this->getMockBuilder(\Doctrine\ORM\Query::class)->disableOriginalConstructor()->getMock();

        $this->entityManager->method('createQueryBuilder')->willReturn($qb);
        $qb->method('select')->willReturnSelf();
        $qb->method('from')->willReturnSelf();
        $qb->method('where')->willReturnSelf();
        $qb->method('setParameter')->willReturnSelf();
        $qb->method('setMaxResults')->willReturnSelf();
        $qb->method('getQuery')->willReturn($query);
        $query->method('getOneOrNullResult')->willReturn($doctrineUser);

        $user = $this->repository->findByProvider('github', 'gh-456');

        $this->assertInstanceOf(User::class, $user);
    }

    // --- delete ---

    public function testDeleteReturnsTrueWhenUserExists(): void
    {
        $doctrineUser = $this->makeDoctrineUser('uuid-1');

        $this->entityRepository->method('find')->with('uuid-1')->willReturn($doctrineUser);
        $this->entityManager->expects($this->once())->method('remove')->with($doctrineUser);
        $this->entityManager->expects($this->once())->method('flush');

        $this->assertTrue($this->repository->delete('uuid-1'));
    }

    public function testDeleteReturnsFalseWhenUserNotFound(): void
    {
        $this->entityRepository->method('find')->willReturn(null);

        $this->assertFalse($this->repository->delete('nonexistent'));
    }

    // --- verifyEmail ---

    public function testVerifyEmailReturnsTrueOnSuccess(): void
    {
        $doctrineUser = $this->makeDoctrineUser('uuid-1');

        $this->entityRepository->method('find')->with('uuid-1')->willReturn($doctrineUser);
        $this->entityManager->expects($this->once())->method('flush');

        $this->assertTrue($this->repository->verifyEmail('uuid-1'));
    }

    public function testVerifyEmailReturnsFalseWhenUserNotFound(): void
    {
        $this->entityRepository->method('find')->willReturn(null);

        $this->assertFalse($this->repository->verifyEmail('nonexistent'));
    }

    // --- update ---

    public function testUpdateThrowsWhenUserNotFound(): void
    {
        $this->entityRepository->method('find')->willReturn(null);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('User not found: nonexistent');

        $this->repository->update('nonexistent', ['email' => 'x@example.com']);
    }

    public function testUpdateModifiesAndReturnsUser(): void
    {
        $doctrineUser = $this->makeDoctrineUser('uuid-1', 'old@example.com');

        $this->entityRepository->method('find')->willReturn($doctrineUser);
        $this->entityManager->expects($this->once())->method('flush');

        $user = $this->repository->update('uuid-1', ['email' => 'new@example.com']);

        $this->assertInstanceOf(User::class, $user);
    }

    // --- generateId ---

    public function testGenerateIdReturnsStringForUuidMode(): void
    {
        $id = $this->repository->generateId();

        $this->assertIsString($id);
        $this->assertNotEmpty($id);
    }

    public function testGenerateIdReturnsNullForAutoIncrementConverter(): void
    {
        // Build a converter configured for int IDs
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($this->entityRepository);
        $metadata = $this->createMock(ClassMetadata::class);
        $em->method('getClassMetadata')->willReturn($metadata);
        $metadata->method('getIdentifierFieldNames')->willReturn(['id']);
        $metadata->method('getTypeOfField')->with('id')->willReturn('integer');

        $intConverter = new UserIdConverter($em, 'App\\Entity\\User');
        $repo = new DoctrineUserRepository($em, $intConverter, 'App\\Entity\\User');

        $this->assertNull($repo->generateId());
    }
}
