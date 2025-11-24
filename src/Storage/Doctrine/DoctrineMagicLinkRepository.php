<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\MagicLinkToken;
use BetterAuth\Core\Interfaces\MagicLinkStorageInterface;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine implementation of MagicLinkStorageInterface.
 *
 * This repository is final to ensure consistent token persistence behavior.
 */
final class DoctrineMagicLinkRepository implements MagicLinkStorageInterface
{
    private string $tokenClass;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        string $tokenClass = MagicLinkToken::class
    ) {
        $this->tokenClass = $tokenClass;
    }

    public function store(string $token, string $email, int $expiresIn): MagicLinkToken
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

        return MagicLinkToken::fromArray([
            'token' => $token,
            'email' => $email,
            'expires_at' => $expiresAt->format('Y-m-d H:i:s'),
            'created_at' => $doctrineToken->getCreatedAt()->format('Y-m-d H:i:s'),
            'used' => false,
        ]);
    }

    public function findByToken(string $token): ?MagicLinkToken
    {
        $doctrineToken = $this->entityManager->getRepository($this->tokenClass)->find($token);

        if ($doctrineToken === null) {
            return null;
        }

        return MagicLinkToken::fromArray([
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

    public function deleteExpired(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete($this->tokenClass, 'mlt')
            ->where('mlt.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable());

        return $qb->getQuery()->execute();
    }
}
