<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\DeviceInfo as CoreDeviceInfo;
use BetterAuth\Core\Interfaces\DeviceInfoRepositoryInterface;
use BetterAuth\Symfony\Model\DeviceInfo;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

final readonly class DoctrineDeviceInfoRepository implements DeviceInfoRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {
    }

    public function generateId(): ?string
    {
        return null;
    }

    public function create(array $data): CoreDeviceInfo
    {
        $device = new DeviceInfo();
        $device->id = $data['id'];
        $device->userId = $data['user_id'];
        $device->fingerprint = $data['fingerprint'];
        $device->deviceType = $data['device_type'] ?? null;
        $device->browser = $data['browser'] ?? null;
        $device->browserVersion = $data['browser_version'] ?? null;
        $device->os = $data['os'] ?? null;
        $device->osVersion = $data['os_version'] ?? null;
        $device->ipAddress = $data['ip_address'] ?? null;
        $device->location = $data['location'] ?? null;
        $device->isTrusted = $data['is_trusted'] ?? false;
        $device->firstSeenAt = new DateTimeImmutable($data['first_seen_at'] ?? 'now');
        $device->lastSeenAt = new DateTimeImmutable($data['last_seen_at'] ?? 'now');
        $device->metadata = $data['metadata'] ?? null;

        $this->entityManager->persist($device);
        $this->entityManager->flush();

        return $this->toCoreEntity($device);
    }

    public function findById(string $id): ?CoreDeviceInfo
    {
        $device = $this->entityManager->find(DeviceInfo::class, $id);

        return $device ? $this->toCoreEntity($device) : null;
    }

    public function findByFingerprint(string $userId, string $fingerprint): ?CoreDeviceInfo
    {
        $device = $this->entityManager->getRepository(DeviceInfo::class)
            ->findOneBy(['userId' => $userId, 'fingerprint' => $fingerprint]);

        return $device ? $this->toCoreEntity($device) : null;
    }

    public function findByUserId(string $userId): array
    {
        $devices = $this->entityManager->getRepository(DeviceInfo::class)
            ->findBy(['userId' => $userId], ['lastSeenAt' => 'DESC']);

        return array_map(fn ($device) => $this->toCoreEntity($device), $devices);
    }

    public function update(string $id, array $data): CoreDeviceInfo
    {
        $device = $this->entityManager->find(DeviceInfo::class, $id);
        if ($device === null) {
            throw new \RuntimeException("Device not found: $id");
        }

        if (isset($data['ip_address'])) {
            $device->ipAddress = $data['ip_address'];
        }
        if (isset($data['location'])) {
            $device->location = $data['location'];
        }
        if (isset($data['is_trusted'])) {
            $device->isTrusted = $data['is_trusted'];
        }
        if (isset($data['last_seen_at'])) {
            $device->lastSeenAt = new DateTimeImmutable($data['last_seen_at']);
        }

        $this->entityManager->flush();

        return $this->toCoreEntity($device);
    }

    public function delete(string $id): bool
    {
        $device = $this->entityManager->find(DeviceInfo::class, $id);
        if ($device === null) {
            return false;
        }

        $this->entityManager->remove($device);
        $this->entityManager->flush();

        return true;
    }

    private function toCoreEntity(DeviceInfo $device): CoreDeviceInfo
    {
        return CoreDeviceInfo::fromArray([
            'id' => $device->id,
            'user_id' => $device->userId,
            'fingerprint' => $device->fingerprint,
            'device_type' => $device->deviceType,
            'browser' => $device->browser,
            'browser_version' => $device->browserVersion,
            'os' => $device->os,
            'os_version' => $device->osVersion,
            'ip_address' => $device->ipAddress,
            'location' => $device->location,
            'is_trusted' => $device->isTrusted,
            'first_seen_at' => $device->firstSeenAt->format('Y-m-d H:i:s'),
            'last_seen_at' => $device->lastSeenAt->format('Y-m-d H:i:s'),
            'metadata' => $device->metadata,
        ]);
    }
}
