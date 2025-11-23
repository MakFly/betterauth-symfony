<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\SuspiciousActivity as CoreSuspiciousActivity;
use BetterAuth\Core\Interfaces\SuspiciousActivityRepositoryInterface;
use BetterAuth\Symfony\Model\SuspiciousActivity;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineSuspiciousActivityRepository implements SuspiciousActivityRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function generateId(): ?string
    {
        return null;
    }

    public function create(array $data): CoreSuspiciousActivity
    {
        $activity = new SuspiciousActivity();
        $activity->id = $data['id'];
        $activity->userId = $data['user_id'];
        $activity->activityType = $data['activity_type'];
        $activity->riskLevel = $data['risk_level'];
        $activity->ipAddress = $data['ip_address'] ?? null;
        $activity->userAgent = $data['user_agent'] ?? null;
        $activity->location = $data['location'] ?? null;
        $activity->detectedAt = new DateTimeImmutable($data['detected_at'] ?? 'now');
        $activity->status = $data['status'] ?? 'pending';
        $activity->resolvedAt = isset($data['resolved_at']) ? new DateTimeImmutable($data['resolved_at']) : null;
        $activity->details = $data['details'] ?? null;

        $this->entityManager->persist($activity);
        $this->entityManager->flush();

        return $this->toCoreEntity($activity);
    }

    public function findById(string $id): ?CoreSuspiciousActivity
    {
        $activity = $this->entityManager->find(SuspiciousActivity::class, $id);

        return $activity ? $this->toCoreEntity($activity) : null;
    }

    public function findByUserId(string $userId, int $limit = 100): array
    {
        $activities = $this->entityManager->getRepository(SuspiciousActivity::class)
            ->findBy(['userId' => $userId], ['detectedAt' => 'DESC'], $limit);

        return array_map(fn ($activity) => $this->toCoreEntity($activity), $activities);
    }

    public function findByStatus(string $status, int $limit = 100): array
    {
        $activities = $this->entityManager->getRepository(SuspiciousActivity::class)
            ->findBy(['status' => $status], ['detectedAt' => 'DESC'], $limit);

        return array_map(fn ($activity) => $this->toCoreEntity($activity), $activities);
    }

    public function update(string $id, array $data): CoreSuspiciousActivity
    {
        $activity = $this->entityManager->find(SuspiciousActivity::class, $id);
        if ($activity === null) {
            throw new \RuntimeException("Suspicious activity not found: $id");
        }

        if (isset($data['status'])) {
            $activity->status = $data['status'];
        }
        if (isset($data['resolved_at'])) {
            $activity->resolvedAt = new DateTimeImmutable($data['resolved_at']);
        }

        $this->entityManager->flush();

        return $this->toCoreEntity($activity);
    }

    public function delete(string $id): bool
    {
        $activity = $this->entityManager->find(SuspiciousActivity::class, $id);
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
            'id' => $activity->id,
            'user_id' => $activity->userId,
            'activity_type' => $activity->activityType,
            'risk_level' => $activity->riskLevel,
            'ip_address' => $activity->ipAddress,
            'user_agent' => $activity->userAgent,
            'location' => $activity->location,
            'detected_at' => $activity->detectedAt->format('Y-m-d H:i:s'),
            'status' => $activity->status,
            'resolved_at' => $activity->resolvedAt?->format('Y-m-d H:i:s'),
            'details' => $activity->details,
        ]);
    }
}
