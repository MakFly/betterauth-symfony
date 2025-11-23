<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\Session;
use BetterAuth\Core\Interfaces\SessionRepositoryInterface;
use BetterAuth\Symfony\Service\UserIdConverter;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

class DoctrineSessionRepository implements SessionRepositoryInterface
{
    private string $sessionClass;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserIdConverter $idConverter,
        string $sessionClass = 'App\\Entity\\Session'
    ) {
        $this->sessionClass = $sessionClass;
    }

    public function findByToken(string $token): ?Session
    {
        $doctrineSession = $this->entityManager->getRepository($this->sessionClass)->find($token);

        if ($doctrineSession === null) {
            return null;
        }

        return $this->toEntity($doctrineSession);
    }

    public function findByUserId(string $userId): array
    {
        $doctrineSessions = $this->entityManager->getRepository($this->sessionClass)
            ->findBy(['userId' => $this->idConverter->toDatabaseId($userId)]);

        return array_map(
            fn ($session) => $this->toEntity($session),
            $doctrineSessions
        );
    }

    public function create(array $data): Session
    {
        $doctrineSession = new ($this->sessionClass)();
        $doctrineSession->setToken($data['token']);
        $doctrineSession->setUserId($this->idConverter->toDatabaseId($data['user_id']));
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

        return $this->toEntity($doctrineSession);
    }

    public function update(string $token, array $data): Session
    {
        $doctrineSession = $this->entityManager->getRepository($this->sessionClass)->find($token);

        if ($doctrineSession === null) {
            throw new \RuntimeException("Session not found: $token");
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

        return $this->toEntity($doctrineSession);
    }

    public function delete(string $token): bool
    {
        $doctrineSession = $this->entityManager->getRepository($this->sessionClass)->find($token);

        if ($doctrineSession === null) {
            return false;
        }

        $this->entityManager->remove($doctrineSession);
        $this->entityManager->flush();

        return true;
    }

    public function deleteByUserId(string $userId): int
    {
        $doctrineSessions = $this->entityManager->getRepository($this->sessionClass)
            ->findBy(['userId' => $this->idConverter->toDatabaseId($userId)]);

        $count = count($doctrineSessions);

        foreach ($doctrineSessions as $session) {
            $this->entityManager->remove($session);
        }

        $this->entityManager->flush();

        return $count;
    }

    public function deleteExpired(): int
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->delete($this->sessionClass, 's')
            ->where('s.expiresAt < :now')
            ->setParameter('now', new DateTimeImmutable());

        return $qb->getQuery()->execute();
    }

    private function toEntity($doctrineSession): Session
    {
        return Session::fromArray([
            'token' => $doctrineSession->getToken(),
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
