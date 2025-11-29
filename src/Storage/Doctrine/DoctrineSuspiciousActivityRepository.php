<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\SuspiciousActivity as CoreSuspiciousActivity;
use BetterAuth\Core\Interfaces\SuspiciousActivityRepositoryInterface;
use BetterAuth\Symfony\Model\SuspiciousActivity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine repository for SuspiciousActivity entities.
 *
 * Requires an entity class that extends BetterAuth\Symfony\Model\SuspiciousActivity.
 */
final readonly class DoctrineSuspiciousActivityRepository implements SuspiciousActivityRepositoryInterface
{
    /**
     * @param string $suspiciousActivityClass FQCN of entity extending SuspiciousActivity
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $suspiciousActivityClass = 'App\\Entity\\SuspiciousActivity'
    ) {
    }

    public function generateId(): ?string
    {
        return null;
    }

    public function create(array $data): CoreSuspiciousActivity
    {
        $class = $this->suspiciousActivityClass;
        /** @var SuspiciousActivity $activity */
        $activity = new $class();
        $activity->setId($data['id']);
        $activity->setUserId($data['user_id']);
        $activity->setActivityType($data['activity_type']);
        $activity->setRiskLevel($data['risk_level']);
        $activity->setIpAddress($data['ip_address'] ?? null);
        $activity->setUserAgent($data['user_agent'] ?? null);
        $activity->setLocation($data['location'] ?? null);
        $activity->setDetectedAt(new DateTimeImmutable($data['detected_at'] ?? 'now'));
        $activity->setStatus($data['status'] ?? 'pending');
        $activity->setResolvedAt(isset($data['resolved_at']) ? new DateTimeImmutable($data['resolved_at']) : null);
        $activity->setDetails($data['details'] ?? null);

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $this->toCoreEntity($activity);
    }

    public function findById(string $id): ?CoreSuspiciousActivity
    {
        $activity = $this->entityManager->find($this->suspiciousActivityClass, $id);

        return $activity ? $this->toCoreEntity($activity) : null;
    }

    public function findByUserId(string $userId, int $limit = 100): array
    {
        $activities = $this->entityManager->getRepository($this->suspiciousActivityClass)
            ->findBy(['userId' => $userId], ['detectedAt' => 'DESC'], $limit);

        return array_map(fn ($activity) => $this->toCoreEntity($activity), $activities);
    }

    public function findByStatus(string $status, int $limit = 100): array
    {
        $activities = $this->entityManager->getRepository($this->suspiciousActivityClass)
            ->findBy(['status' => $status], ['detectedAt' => 'DESC'], $limit);

        return array_map(fn ($activity) => $this->toCoreEntity($activity), $activities);
    }

    public function update(string $id, array $data): CoreSuspiciousActivity
    {
        /** @var SuspiciousActivity|null $activity */
        $activity = $this->entityManager->find($this->suspiciousActivityClass, $id);
        if ($activity === null) {
            throw new \RuntimeException("Suspicious activity not found: $id");
        }

        if (isset($data['status'])) {
            $activity->setStatus($data['status']);
        }
        if (isset($data['resolved_at'])) {
            $activity->setResolvedAt(new DateTimeImmutable($data['resolved_at']));
        }

        $this->entityManager->flush();

        return $this->toCoreEntity($activity);
    }

    public function delete(string $id): bool
    {
        $activity = $this->entityManager->find($this->suspiciousActivityClass, $id);
        if ($activity === null) {
            return false;
        }

        $this->entityManager->remove($activity);
        $this->entityManager->flush();

        return true;
    }

    private function toCoreEntity(SuspiciousActivity $activity): CoreSuspiciousActivity
    {
        return CoreSuspiciousActivity::fromArray([
            'id' => (string) $activity->getId(),
            'user_id' => (string) $activity->getUserId(),
            'activity_type' => $activity->getActivityType(),
            'risk_level' => $activity->getRiskLevel(),
            'ip_address' => $activity->getIpAddress(),
            'user_agent' => $activity->getUserAgent(),
            'location' => $activity->getLocation(),
            'detected_at' => $activity->getDetectedAt()->format('Y-m-d H:i:s'),
            'status' => $activity->getStatus(),
            'resolved_at' => $activity->getResolvedAt()?->format('Y-m-d H:i:s'),
            'details' => $activity->getDetails(),
        ]);
    }
}
