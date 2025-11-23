<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\SessionActivity as CoreSessionActivity;
use BetterAuth\Core\Interfaces\SessionActivityRepositoryInterface;
use BetterAuth\Symfony\Model\SessionActivity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineSessionActivityRepository implements SessionActivityRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function generateId(): ?string
    {
        return null;
    }

    public function create(array $data): CoreSessionActivity
    {
        $activity = new SessionActivity();
        $activity->id = $data['id'];
        $activity->sessionId = $data['session_id'];
        $activity->action = $data['action'];
        $activity->ipAddress = $data['ip_address'] ?? null;
        $activity->userAgent = $data['user_agent'] ?? null;
        $activity->location = $data['location'] ?? null;
        $activity->createdAt = new DateTimeImmutable($data['created_at'] ?? 'now');
        $activity->metadata = $data['metadata'] ?? null;

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $this->toCoreEntity($activity);
    }

    public function findById(string $id): ?CoreSessionActivity
    {
        $activity = $this->entityManager->find(SessionActivity::class, $id);

        return $activity ? $this->toCoreEntity($activity) : null;
    }

    public function findBySessionId(string $sessionId, int $limit = 50): array
    {
        $activities = $this->entityManager->getRepository(SessionActivity::class)
            ->findBy(['sessionId' => $sessionId], ['createdAt' => 'DESC'], $limit);

        return array_map(fn ($activity) => $this->toCoreEntity($activity), $activities);
    }

    public function findByUserId(string $userId, int $limit = 100): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $activities = $qb->select('sa')
            ->from(SessionActivity::class, 'sa')
            ->join('BetterAuth\Symfony\Model\Session', 's', 'WITH', 'sa.sessionId = s.id')
            ->where('s.userId = :userId')
            ->setParameter('userId', $userId)
            ->orderBy('sa.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        return array_map(fn ($activity) => $this->toCoreEntity($activity), $activities);
    }

    public function delete(string $id): bool
    {
        $activity = $this->entityManager->find(SessionActivity::class, $id);
        if ($activity === null) {
            return false;
        }

        $this->entityManager->remove($activity);
        $this->entityManager->flush();

        return true;
    }

    public function deleteBySessionId(string $sessionId): int
    {
        $qb = $this->entityManager->createQueryBuilder();

        return $qb->delete(SessionActivity::class, 'sa')
            ->where('sa.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->execute();
    }

    private function toCoreEntity(SessionActivity $activity): CoreSessionActivity
    {
        return CoreSessionActivity::fromArray([
            'id' => $activity->id,
            'session_id' => $activity->sessionId,
            'action' => $activity->action,
            'ip_address' => $activity->ipAddress,
            'user_agent' => $activity->userAgent,
            'location' => $activity->location,
            'created_at' => $activity->createdAt->format('Y-m-d H:i:s'),
            'metadata' => $activity->metadata,
        ]);
    }
}
