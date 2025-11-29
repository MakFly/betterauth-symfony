<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\SecurityEvent as CoreSecurityEvent;
use BetterAuth\Core\Interfaces\SecurityEventRepositoryInterface;
use BetterAuth\Symfony\Model\SecurityEvent;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine repository for SecurityEvent entities.
 *
 * Requires an entity class that extends BetterAuth\Symfony\Model\SecurityEvent.
 */
final readonly class DoctrineSecurityEventRepository implements SecurityEventRepositoryInterface
{
    /**
     * @param string $securityEventClass FQCN of entity extending SecurityEvent
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $securityEventClass = 'App\\Entity\\SecurityEvent'
    ) {
    }

    public function generateId(): ?string
    {
        return null;
    }

    public function create(array $data): CoreSecurityEvent
    {
        $class = $this->securityEventClass;
        /** @var SecurityEvent $event */
        $event = new $class();
        $event->setId($data['id']);
        $event->setUserId($data['user_id']);
        $event->setEventType($data['event_type']);
        $event->setSeverity($data['severity']);
        $event->setIpAddress($data['ip_address'] ?? null);
        $event->setUserAgent($data['user_agent'] ?? null);
        $event->setLocation($data['location'] ?? null);
        $event->setCreatedAt(new DateTimeImmutable($data['created_at'] ?? 'now'));
        $event->setDetails($data['details'] ?? null);

        $this->entityManager->persist($event);
        $this->entityManager->flush();

        return $this->toCoreEntity($event);
    }

    public function findById(string $id): ?CoreSecurityEvent
    {
        $event = $this->entityManager->find($this->securityEventClass, $id);

        return $event ? $this->toCoreEntity($event) : null;
    }

    public function findByUserId(string $userId, int $limit = 100): array
    {
        $events = $this->entityManager->getRepository($this->securityEventClass)
            ->findBy(['userId' => $userId], ['createdAt' => 'DESC'], $limit);

        return array_map(fn ($event) => $this->toCoreEntity($event), $events);
    }

    public function findBySeverity(string $severity, int $limit = 100): array
    {
        $events = $this->entityManager->getRepository($this->securityEventClass)
            ->findBy(['severity' => $severity], ['createdAt' => 'DESC'], $limit);

        return array_map(fn ($event) => $this->toCoreEntity($event), $events);
    }

    public function delete(string $id): bool
    {
        $event = $this->entityManager->find($this->securityEventClass, $id);
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
            'id' => (string) $event->getId(),
            'user_id' => (string) $event->getUserId(),
            'event_type' => $event->getEventType(),
            'severity' => $event->getSeverity(),
            'ip_address' => $event->getIpAddress(),
            'user_agent' => $event->getUserAgent(),
            'location' => $event->getLocation(),
            'created_at' => $event->getCreatedAt()->format('Y-m-d H:i:s'),
            'details' => $event->getDetails(),
        ]);
    }
}
