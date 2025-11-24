<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Doctrine\PasswordResetTokenEntity;
use BetterAuth\Core\Entities\PasswordResetToken;
use BetterAuth\Core\Interfaces\PasswordResetStorageInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine implementation of PasswordResetStorageInterface.
 *
 * This repository is final to ensure consistent token persistence behavior.
 */
final class DoctrinePasswordResetRepository implements PasswordResetStorageInterface
{
    private string $tokenClass;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        string $tokenClass = PasswordResetTokenEntity::class
    ) {
        $this->tokenClass = $tokenClass;
    }

    public function store(string $token, string $email, int $expiresIn): PasswordResetToken
    {
        $expiresAt = (new DateTimeImmutable())->modify("+{$expiresIn} seconds");

        $doctrineToken = new ($this->tokenClass)();
        $doctrineToken->setToken($token);
        $doctrineToken->setEmail($email);
        $doctrineToken->setExpiresAt($expiresAt);
        $doctrineToken->setCreatedAt(new DateTimeImmutable());
        $doctrineToken->setUsed(false);

        $this->entityManager->persist($doctrineToken);
        $this->entityManager->flush();

        return PasswordResetToken::fromArray([
            'token' => $token,
            'email' => $email,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'created_at' => $doctrineToken->getCreatedAt()->format('Y-m-d H:i:s'),
            'used' => false,
        ]);
    }

    public function findByToken(string $token): ?PasswordResetToken
    {
        $doctrineToken = $this->entityManager->getRepository($this->tokenClass)->find($token);

        if ($doctrineToken === null) {
            return null;
        }

        return PasswordResetToken::fromArray([
            'token' => $doctrineToken->getToken(),
            'email' => $doctrineToken->getEmail(),
            'expires_at' => $doctrineToken->getExpiresAt()->format('Y-m-d H:i:s'),
            'created_at' => $doctrineToken->getCreatedAt()->format('Y-m-d H:i:s'),
            'used' => $doctrineToken->isUsed(),
        ]);
    }

    public function markAsUsed(string $token): bool
    {
        $doctrineToken = $this->entityManager->getRepository($this->tokenClass)->find($token);

        if ($doctrineToken === null) {
            return false;
        }

        $doctrineToken->setUsed(true);
        $this->entityManager->flush();

        return true;
    }

    public function delete(string $token): bool
    {
        $doctrineToken = $this->entityManager->getRepository($this->tokenClass)->find($token);

        if ($doctrineToken === null) {
            return false;
        }

        $this->entityManager->remove($doctrineToken);
        $this->entityManager->flush();

        return true;
    }

    public function deleteByEmail(string $email): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete($this->tokenClass, 'prt')
            ->where('prt.email = :email')
            ->setParameter('email', $email);

        return $qb->getQuery()->execute();
    }

    public function deleteExpired(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete($this->tokenClass, 'prt')
            ->where('prt.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable());

        return $qb->getQuery()->execute();
    }
}
