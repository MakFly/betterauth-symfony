<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\RefreshToken;
use BetterAuth\Core\Entities\SimpleRefreshToken;
use BetterAuth\Core\Interfaces\RefreshTokenRepositoryInterface;
use BetterAuth\Symfony\Model\RefreshToken as RefreshTokenModel;
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
    /** @var class-string<RefreshTokenModel> */
    private string $refreshTokenClass;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserIdConverter $idConverter,
        string $refreshTokenClass = RefreshToken::class
    ) {
        /** @var class-string<RefreshTokenModel> $refreshTokenClass */
        $this->refreshTokenClass = $refreshTokenClass;
    }

    public function findByToken(string $token): ?RefreshToken
    {
        $hashedToken = hash('sha256', $token);
        /** @var RefreshTokenModel|null $doctrineToken */
        $doctrineToken = $this->entityManager->getRepository($this->refreshTokenClass)->find($hashedToken);

        if ($doctrineToken === null) {
            return null;
        }

        return $this->toEntity($doctrineToken);
    }

    /**
     * @return array<RefreshToken>
     */
    public function findByUserId(string $userId): array
    {
        /** @var array<RefreshTokenModel> $doctrineTokens */
        $doctrineTokens = $this->entityManager->getRepository($this->refreshTokenClass)
            ->findBy(['userId' => $this->idConverter->toDatabaseId($userId)]);

        return array_map(
            fn (object $token) => $this->toEntity($token),
            $doctrineTokens
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): RefreshToken
    {
        $rawToken = $data['token'];
        $doctrineToken = new ($this->refreshTokenClass)();
        $doctrineToken->setToken(hash('sha256', $rawToken));
        // Support both user_id and userId formats - convert to native type
        $userId = $data['user_id'] ?? $data['userId'];
        $doctrineToken->setUserId((string) $this->idConverter->toDatabaseId($userId));
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

        // Return the raw token to the caller, not the stored hash
        return $this->toEntity($doctrineToken, $rawToken);
    }

    public function revoke(string $token, ?string $replacedBy = null): bool
    {
        $hashedToken = hash('sha256', $token);
        /** @var RefreshTokenModel|null $doctrineToken */
        $doctrineToken = $this->entityManager->getRepository($this->refreshTokenClass)->find($hashedToken);

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

    public function consume(string $token, ?string $replacedBy = null): ?RefreshToken
    {
        $hashedToken = hash('sha256', $token);
        $qb = $this->entityManager->createQueryBuilder();
        $qb->update($this->refreshTokenClass, 'rt')
            ->set('rt.revoked', ':revoked')
            ->set('rt.replacedBy', ':replacedBy')
            ->where('rt.token = :token')
            ->andWhere('rt.revoked = :currentRevoked')
            ->andWhere('rt.expiresAt > :now')
            ->setParameter('revoked', true)
            ->setParameter('replacedBy', $replacedBy)
            ->setParameter('token', $hashedToken)
            ->setParameter('currentRevoked', false)
            ->setParameter('now', new DateTimeImmutable());

        $updated = $qb->getQuery()->execute();
        if ($updated === 0) {
            return null;
        }

        return $this->findByToken($token);
    }

    public function revokeAllForUser(string $userId): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->update($this->refreshTokenClass, 'rt')
            ->set('rt.revoked', ':revoked')
            ->where('rt.userId = :userId')
            ->andWhere('rt.revoked = :currentRevoked')
            ->setParameter('revoked', true)
            ->setParameter('userId', $this->idConverter->toDatabaseId($userId))
            ->setParameter('currentRevoked', false);

        return $qb->getQuery()->execute();
    }

    public function deleteExpired(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete($this->refreshTokenClass, 'rt')
            ->where('rt.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable());

        return $qb->getQuery()->execute();
    }

    /** @param RefreshTokenModel $doctrineToken */
    private function toEntity(object $doctrineToken, ?string $rawToken = null): RefreshToken
    {
        return SimpleRefreshToken::fromArray([
            // When creating, return the raw token to the caller (not the stored hash)
            'token' => $rawToken ?? $doctrineToken->getToken(),
            'user_id' => $this->idConverter->toAuthId($doctrineToken->getUserId()),
            'expires_at' => $doctrineToken->getExpiresAt()->format('Y-m-d H:i:s'),
            'created_at' => $doctrineToken->getCreatedAt()->format('Y-m-d H:i:s'),
            'revoked' => $doctrineToken->isRevoked(),
            'replaced_by' => $doctrineToken->getReplacedBy(),
        ]);
    }
}
