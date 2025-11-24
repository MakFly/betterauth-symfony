<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\RefreshToken;
use BetterAuth\Core\Interfaces\RefreshTokenRepositoryInterface;
use BetterAuth\Symfony\Service\UserIdConverter;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine implementation of RefreshTokenRepositoryInterface.
 *
 * This repository is final to ensure consistent token persistence behavior.
 */
final class DoctrineRefreshTokenRepository implements RefreshTokenRepositoryInterface
{
    private string $refreshTokenClass;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserIdConverter $idConverter,
        string $refreshTokenClass = 'App\\Entity\\RefreshToken'
    ) {
        $this->refreshTokenClass = $refreshTokenClass;
    }

    public function findByToken(string $token): ?RefreshToken
    {
        $doctrineToken = $this->entityManager->getRepository($this->refreshTokenClass)->find($token);

        if ($doctrineToken === null) {
            return null;
        }

        return $this->toEntity($doctrineToken);
    }

    public function findByUserId(string $userId): array
    {
        $doctrineTokens = $this->entityManager->getRepository($this->refreshTokenClass)
            ->findBy(['userId' => $this->idConverter->toDatabaseId($userId)]);

        return array_map(
            fn ($token) => $this->toEntity($token),
            $doctrineTokens
        );
    }

    public function create(array $data): RefreshToken
    {
        $doctrineToken = new ($this->refreshTokenClass)();
        $doctrineToken->setToken($data['token']);
        // Support both user_id and userId formats - convert to native type
        $userId = $data['user_id'] ?? $data['userId'];
        $doctrineToken->setUserId($this->idConverter->toDatabaseId($userId));
        // Support both expires_at and expiresAt formats
        $expiresAt = $data['expires_at'] ?? $data['expiresAt'];
        $doctrineToken->setExpiresAt(
            $expiresAt instanceof DateTimeImmutable
                ? $expiresAt
                : new DateTimeImmutable($expiresAt)
        );
        $doctrineToken->setRevoked($data['revoked'] ?? false);
        $doctrineToken->setReplacedBy($data['replaced_by'] ?? $data['replacedBy'] ?? null);

        $this->entityManager->persist($doctrineToken);
        $this->entityManager->flush();

        return $this->toEntity($doctrineToken);
    }

    public function revoke(string $token, ?string $replacedBy = null): bool
    {
        $doctrineToken = $this->entityManager->getRepository($this->refreshTokenClass)->find($token);

        if ($doctrineToken === null) {
            return false;
        }

        $doctrineToken->setRevoked(true);
        if ($replacedBy !== null) {
            $doctrineToken->setReplacedBy($replacedBy);
        }

        $this->entityManager->flush();

        return true;
    }

    public function revokeAllForUser(string $userId): int
    {
        $doctrineTokens = $this->entityManager->getRepository($this->refreshTokenClass)
            ->findBy(['userId' => $this->idConverter->toDatabaseId($userId), 'revoked' => false]);

        $count = count($doctrineTokens);

        foreach ($doctrineTokens as $token) {
            $token->setRevoked(true);
        }

        $this->entityManager->flush();

        return $count;
    }

    public function deleteExpired(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete($this->refreshTokenClass, 'rt')
            ->where('rt.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable());

        return $qb->getQuery()->execute();
    }

    private function toEntity($doctrineToken): RefreshToken
    {
        return RefreshToken::fromArray([
            'token' => $doctrineToken->getToken(),
            'user_id' => $this->idConverter->toAuthId($doctrineToken->getUserId()),
            'expires_at' => $doctrineToken->getExpiresAt()->format('Y-m-d H:i:s'),
            'created_at' => $doctrineToken->getCreatedAt()->format('Y-m-d H:i:s'),
            'revoked' => $doctrineToken->isRevoked(),
            'replaced_by' => $doctrineToken->getReplacedBy(),
        ]);
    }
}
