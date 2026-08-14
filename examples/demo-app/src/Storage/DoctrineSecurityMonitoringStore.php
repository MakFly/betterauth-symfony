<?php

declare(strict_types=1);

namespace Demo\Storage;

use BetterAuth\Symfony\Feature\Port\SecurityMonitoringStoreInterface;
use Doctrine\DBAL\Connection;

final readonly class DoctrineSecurityMonitoringStore implements SecurityMonitoringStoreInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /** @param array<string, mixed> $event */
    public function record(string $userIdentifier, array $event): void
    {
        $type = $event['type'] ?? null;
        $severity = $event['severity'] ?? null;
        if (!is_string($type) || !is_string($severity)) {
            throw new \InvalidArgumentException('Monitoring event type and severity are required.');
        }
        $this->connection->insert('demo_security_event', [
            'user_identifier' => $userIdentifier,
            'type' => $type,
            'severity' => $severity,
            'details' => json_encode($event['details'] ?? [], JSON_THROW_ON_ERROR),
            'occurred_at' => is_string($event['occurred_at'] ?? null) ? $event['occurred_at'] : (new \DateTimeImmutable())->format(DATE_ATOM),
        ]);
    }
}
