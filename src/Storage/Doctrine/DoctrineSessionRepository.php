<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\Session;
use BetterAuth\Core\Entities\SimpleSession;
use BetterAuth\Core\Interfaces\SessionRepositoryInterface;
use BetterAuth\Symfony\Model\Session as SessionModel;
use BetterAuth\Symfony\Service\UserIdConverter;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine implementation of SessionRepositoryInterface.
 *
 * This repository is final to ensure consistent session persistence behavior.
 */
final class DoctrineSessionRepository implements SessionRepositoryInterface
{
    /** @var class-string<SessionModel> */
    private string $sessionClass;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserIdConverter $idConverter,
        string $sessionClass = Session::class
    ) {
        /** @var class-string<SessionModel> $sessionClass */
        $this->sessionClass = $sessionClass;
    }

    public function findByToken(string $token): ?Session
    {
        $doctrineSession = $this->findModelByToken($token);

        if ($doctrineSession === null) {
            return null;
        }

        return $this->toEntity($doctrineSession, $token);
    }

    /**
     * Hash a token for at-rest storage / lookup (defense against DB read compromise).
     */
    private function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    /**
     * Resolve a session by its plaintext token.
     *
     * Looks up by hash first; if a legacy plaintext row is found it is transparently
     * migrated in place (rehashed) so existing sessions keep working without a forced
     * logout. This makes the at-rest hashing self-migrating after deployment.
     *
     * @return SessionModel|null
     */
    private function findModelByToken(string $token): ?object
    {
        $repository = $this->entityManager->getRepository($this->sessionClass);

        /** @var SessionModel|null $doctrineSession */
        $doctrineSession = $repository->find($this->hashToken($token));
        if ($doctrineSession !== null) {
            return $doctrineSession;
        }

        // Legacy fallback: row still stored with the plaintext token.
        /** @var SessionModel|null $legacy */
        $legacy = $repository->find($token);
        if ($legacy === null) {
            return null;
        }

        // Migrate the identifier in place via raw SQL (avoids ORM identifier-mutation issues),
        // then detach the stale managed entity and reload the migrated row.
        $meta = $this->entityManager->getClassMetadata($this->sessionClass);
        $table = $meta->getTableName();
        $column = $meta->getColumnName('token');
        $this->entityManager->getConnection()->executeStatement(
            "UPDATE {$table} SET {$column} = ? WHERE {$column} = ?",
            [$this->hashToken($token), $token]
        );
        $this->entityManager->detach($legacy);

        /** @var SessionModel|null $migrated */
        $migrated = $repository->find($this->hashToken($token));

        return $migrated;
    }

    /**
     * @return array<Session>
     */
    public function findByUserId(string $userId): array
    {
        /** @var array<SessionModel> $doctrineSessions */
        $doctrineSessions = $this->entityManager->getRepository($this->sessionClass)
            ->findBy(['userId' => $this->idConverter->toDatabaseId($userId)]);

        return array_map(
            fn (object $session) => $this->toEntity($session),
            $doctrineSessions
        );
    }

    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data): Session
    {
        $rawToken = $data['token'];
        $doctrineSession = new ($this->sessionClass)();
        $doctrineSession->setToken($this->hashToken($rawToken));
        $doctrineSession->setUserId((string) $this->idConverter->toDatabaseId($data['user_id']));
        $doctrineSession->setExpiresAt(
            $data['expires_at'] instanceof DateTimeImmutable
                ? $data['expires_at']
                : new DateTimeImmutable($data['expires_at'])
        );
        $doctrineSession->setIpAddress($data['ip_address']);
        $doctrineSession->setUserAgent($data['user_agent']);
        $doctrineSession->setMetadata($data['metadata'] ?? null);
        $doctrineSession->setActiveOrganizationId($data['active_organization_id'] ?? null);
        $doctrineSession->setActiveTeamId($data['active_team_id'] ?? null);

        $this->entityManager->persist($doctrineSession);
        $this->entityManager->flush();

        return $this->toEntity($doctrineSession, $rawToken);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $token, array $data): Session
    {
        $doctrineSession = $this->findModelByToken($token);

        if ($doctrineSession === null) {
            throw new \RuntimeException('Session not found');
        }

        if (isset($data['expires_at'])) {
            $doctrineSession->setExpiresAt(
                $data['expires_at'] instanceof DateTimeImmutable
                    ? $data['expires_at']
                    : new DateTimeImmutable($data['expires_at'])
            );
        }
        if (isset($data['metadata'])) {
            $doctrineSession->setMetadata($data['metadata']);
        }
        if (isset($data['active_organization_id'])) {
            $doctrineSession->setActiveOrganizationId($data['active_organization_id']);
        }
        if (isset($data['active_team_id'])) {
            $doctrineSession->setActiveTeamId($data['active_team_id']);
        }

        $doctrineSession->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $this->toEntity($doctrineSession, $token);
    }

    public function delete(string $token): bool
    {
        $doctrineSession = $this->findModelByToken($token);

        if ($doctrineSession === null) {
            return false;
        }

        $this->entityManager->remove($doctrineSession);
        $this->entityManager->flush();

        return true;
    }

    public function deleteByUserId(string $userId): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete($this->sessionClass, 's')
            ->where('s.userId = :userId')
            ->setParameter('userId', $this->idConverter->toDatabaseId($userId));

        return $qb->getQuery()->execute();
    }

    public function deleteExpired(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete($this->sessionClass, 's')
            ->where('s.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable());

        return $qb->getQuery()->execute();
    }

    /** @param SessionModel $doctrineSession */
    private function toEntity(object $doctrineSession, ?string $rawToken = null): Session
    {
        return SimpleSession::fromArray([
            // Only the hash is stored; expose the plaintext token to callers when known.
            'token' => $rawToken ?? $doctrineSession->getToken(),
            'user_id' => $this->idConverter->toAuthId($doctrineSession->getUserId()),
            'expires_at' => $doctrineSession->getExpiresAt()->format('Y-m-d H:i:s'),
            'ip_address' => $doctrineSession->getIpAddress(),
            'user_agent' => $doctrineSession->getUserAgent(),
            'created_at' => $doctrineSession->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $doctrineSession->getUpdatedAt()->format('Y-m-d H:i:s'),
            'metadata' => $doctrineSession->getMetadata(),
            'active_organization_id' => $doctrineSession->getActiveOrganizationId(),
            'active_team_id' => $doctrineSession->getActiveTeamId(),
        ]);
    }
}
