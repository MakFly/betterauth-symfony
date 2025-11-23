<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\SecurityEvent as CoreSecurityEvent;
use BetterAuth\Core\Interfaces\SecurityEventRepositoryInterface;
use BetterAuth\Symfony\Model\SecurityEvent;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineSecurityEventRepository implements SecurityEventRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function generateId(): ?string
    {
        return null;
    }

    public function create(array $data): CoreSecurityEvent
    {
        $event = new SecurityEvent();
        $event->id = $data['id'];
        $event->userId = $data['user_id'];
        $event->eventType = $data['event_type'];
        $event->severity = $data['severity'];
        $event->ipAddress = $data['ip_address'] ?? null;
        $event->userAgent = $data['user_agent'] ?? null;
        $event->location = $data['location'] ?? null;
        $event->createdAt = new DateTimeImmutable($data['created_at'] ?? 'now');
        $event->details = $data['details'] ?? null;

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $this->toCoreEntity($event);
    }

    public function findById(string $id): ?CoreSecurityEvent
    {
        $event = $this->entityManager->find(SecurityEvent::class, $id);

        return $event ? $this->toCoreEntity($event) : null;
    }

    public function findByUserId(string $userId, int $limit = 100): array
    {
        $events = $this->entityManager->getRepository(SecurityEvent::class)
            ->findBy(['userId' => $userId], ['createdAt' => 'DESC'], $limit);

        return array_map(fn ($event) => $this->toCoreEntity($event), $events);
    }

    public function findBySeverity(string $severity, int $limit = 100): array
    {
        $events = $this->entityManager->getRepository(SecurityEvent::class)
            ->findBy(['severity' => $severity], ['createdAt' => 'DESC'], $limit);

        return array_map(fn ($event) => $this->toCoreEntity($event), $events);
    }

    public function delete(string $id): bool
    {
        $event = $this->entityManager->find(SecurityEvent::class, $id);
        if ($event === null) {
            return false;
        }

        $this->entityManager->remove($event);
        $this->entityManager->flush();

        return true;
    }

    private function toCoreEntity(SecurityEvent $event): CoreSecurityEvent
    {
        return CoreSecurityEvent::fromArray([
            'id' => $event->id,
            'user_id' => $event->userId,
            'event_type' => $event->eventType,
            'severity' => $event->severity,
            'ip_address' => $event->ipAddress,
            'user_agent' => $event->userAgent,
            'location' => $event->location,
            'created_at' => $event->createdAt->format('Y-m-d H:i:s'),
            'details' => $event->details,
        ]);
    }
}
