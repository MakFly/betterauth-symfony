<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\SessionActivity as CoreSessionActivity;
use BetterAuth\Core\Interfaces\SessionActivityRepositoryInterface;
use BetterAuth\Symfony\Model\SessionActivity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine repository for SessionActivity entities.
 *
 * Requires an entity class that extends BetterAuth\Symfony\Model\SessionActivity.
 */
final readonly class DoctrineSessionActivityRepository implements SessionActivityRepositoryInterface
{
    /**
     * @param string $sessionActivityClass FQCN of entity extending SessionActivity
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $sessionActivityClass = 'App\\Entity\\SessionActivity'
    ) {
    }

    public function generateId(): ?string
    {
        return null;
    }

    public function create(array $data): CoreSessionActivity
    {
        $class = $this->sessionActivityClass;
        /** @var SessionActivity $activity */
        $activity = new $class();
        $activity->setId($data['id']);
        $activity->setSessionId($data['session_id']);
        $activity->setAction($data['action']);
        $activity->setIpAddress($data['ip_address'] ?? null);
        $activity->setUserAgent($data['user_agent'] ?? null);
        $activity->setLocation($data['location'] ?? null);
        $activity->setCreatedAt(new DateTimeImmutable($data['created_at'] ?? 'now'));
        $activity->setMetadata($data['metadata'] ?? null);

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $this->toCoreEntity($activity);
    }

    public function findById(string $id): ?CoreSessionActivity
    {
        $activity = $this->entityManager->find($this->sessionActivityClass, $id);

        return $activity ? $this->toCoreEntity($activity) : null;
    }

    public function findBySessionId(string $sessionId, int $limit = 50): array
    {
        $activities = $this->entityManager->getRepository($this->sessionActivityClass)
            ->findBy(['sessionId' => $sessionId], ['createdAt' => 'DESC'], $limit);

        return array_map(fn ($activity) => $this->toCoreEntity($activity), $activities);
    }

    public function findByUserId(string $userId, int $limit = 100): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $activities = $qb->select('sa')
            ->from($this->sessionActivityClass, 'sa')
            ->join('App\\Entity\\Session', 's', 'WITH', 'sa.sessionId = s.token')
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
        $activity = $this->entityManager->find($this->sessionActivityClass, $id);
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

        return $qb->delete($this->sessionActivityClass, 'sa')
            ->where('sa.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->getQuery()
            ->execute();
    }

    private function toCoreEntity(SessionActivity $activity): CoreSessionActivity
    {
        return CoreSessionActivity::fromArray([
            'id' => (string) $activity->getId(),
            'session_id' => $activity->getSessionId(),
            'action' => $activity->getAction(),
            'ip_address' => $activity->getIpAddress(),
            'user_agent' => $activity->getUserAgent(),
            'location' => $activity->getLocation(),
            'created_at' => $activity->getCreatedAt()->format('Y-m-d H:i:s'),
            'metadata' => $activity->getMetadata(),
        ]);
    }
}
