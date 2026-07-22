<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Storage;

use BetterAuth\Core\Entities\EmailVerificationToken as CoreEmailVerificationToken;
use BetterAuth\Core\Entities\MagicLinkToken as CoreMagicLinkToken;
use BetterAuth\Core\Entities\PasswordResetToken as CorePasswordResetToken;
use BetterAuth\Symfony\Storage\Doctrine\DoctrineEmailVerificationRepository;
use BetterAuth\Symfony\Storage\Doctrine\DoctrineMagicLinkRepository;
use BetterAuth\Symfony\Storage\Doctrine\DoctrinePasswordResetRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * SEC-02 — magic-link, password-reset and email-verification tokens must be
 * hashed (SHA-256) before persistence and looked up by hash, mirroring the
 * refresh-token repository. Guards against replay after a DB read compromise.
 */
final class DoctrineTokenAtRestHashingTest extends TestCase
{
    private EntityManagerInterface&MockObject $entityManager;
    private EntityRepository&MockObject $entityRepository;

    protected function setUp(): void
    {
        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityRepository = $this->createMock(EntityRepository::class);
        $this->entityManager->method('getRepository')->willReturn($this->entityRepository);
    }

    private function capturePersistedToken(): object
    {
        $captured = new \stdClass();
        $captured->entity = null;
        $this->entityManager->method('persist')->willReturnCallback(
            function (object $entity) use ($captured): void {
                $captured->entity = $entity;
            }
        );

        return $captured;
    }

    public function testMagicLinkStoreHashesTokenAndLooksUpByHash(): void
    {
        $captured = $this->capturePersistedToken();
        $repo = new DoctrineMagicLinkRepository($this->entityManager, CoreMagicLinkToken::class);

        $raw = 'magic-raw';
        $repo->store($raw, 'user@example.com', 600);

        self::assertSame(hash('sha256', $raw), $captured->entity->getToken());

        $this->entityRepository->expects($this->once())
            ->method('find')->with(hash('sha256', $raw))->willReturn(null);
        self::assertNull($repo->findByToken($raw));
    }

    public function testPasswordResetStoreHashesTokenAndLooksUpByHash(): void
    {
        $captured = $this->capturePersistedToken();
        $repo = new DoctrinePasswordResetRepository($this->entityManager, CorePasswordResetToken::class);

        $raw = 'reset-raw';
        $repo->store($raw, 'user@example.com', 600);

        self::assertSame(hash('sha256', $raw), $captured->entity->getToken());

        $this->entityRepository->expects($this->once())
            ->method('find')->with(hash('sha256', $raw))->willReturn(null);
        self::assertNull($repo->findByToken($raw));
    }

    public function testEmailVerificationStoreHashesTokenAndLooksUpByHash(): void
    {
        $captured = $this->capturePersistedToken();
        $repo = new DoctrineEmailVerificationRepository($this->entityManager, CoreEmailVerificationToken::class);

        $raw = 'verif-raw';
        $repo->store($raw, 'user@example.com', 600);

        self::assertSame(hash('sha256', $raw), $captured->entity->getToken());

        $this->entityRepository->expects($this->once())
            ->method('find')->with(hash('sha256', $raw))->willReturn(null);
        self::assertNull($repo->findByToken($raw));
    }
}
