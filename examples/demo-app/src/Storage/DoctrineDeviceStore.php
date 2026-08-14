<?php

declare(strict_types=1);

namespace Demo\Storage;

use BetterAuth\Symfony\Feature\Port\DeviceStoreInterface;
use Doctrine\DBAL\Connection;

final readonly class DoctrineDeviceStore implements DeviceStoreInterface
{
    public function __construct(private Connection $connection)
    {
    }

    /** @param array<string, scalar|null> $device */
    public function record(string $userIdentifier, array $device): void
    {
        $fingerprint = $device['fingerprint'] ?? null;
        if (!is_string($fingerprint)) {
            throw new \InvalidArgumentException('A device fingerprint is required.');
        }
        $this->connection->insert('demo_device', [
            'user_identifier' => $userIdentifier,
            'fingerprint' => $fingerprint,
            'user_agent' => is_string($device['user_agent'] ?? null) ? $device['user_agent'] : null,
            'ip_address' => is_string($device['ip_address'] ?? null) ? $device['ip_address'] : null,
            'attributes' => json_encode($device, JSON_THROW_ON_ERROR),
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s.u'),
        ]);
    }
}
