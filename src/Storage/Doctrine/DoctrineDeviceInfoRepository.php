<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\DeviceInfo as CoreDeviceInfo;
use BetterAuth\Core\Interfaces\DeviceInfoRepositoryInterface;
use BetterAuth\Symfony\Model\DeviceInfo;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine repository for DeviceInfo entities.
 *
 * Requires an entity class that extends BetterAuth\Symfony\Model\DeviceInfo.
 */
final readonly class DoctrineDeviceInfoRepository implements DeviceInfoRepositoryInterface
{
    /**
     * @param string $deviceInfoClass FQCN of entity extending DeviceInfo
     */
    public function __construct(
        private EntityManagerInterface $entityManager,
        private string $deviceInfoClass = 'App\\Entity\\DeviceInfo'
    ) {
    }

    public function generateId(): ?string
    {
        return null;
    }

    public function create(array $data): CoreDeviceInfo
    {
        $class = $this->deviceInfoClass;
        /** @var DeviceInfo $device */
        $device = new $class();
        $device->setId($data['id']);
        $device->setUserId($data['user_id']);
        $device->setFingerprint($data['fingerprint']);
        $device->setDeviceType($data['device_type'] ?? null);
        $device->setBrowser($data['browser'] ?? null);
        $device->setBrowserVersion($data['browser_version'] ?? null);
        $device->setOs($data['os'] ?? null);
        $device->setOsVersion($data['os_version'] ?? null);
        $device->setIpAddress($data['ip_address'] ?? null);
        $device->setLocation($data['location'] ?? null);
        $device->setIsTrusted($data['is_trusted'] ?? false);
        $device->setFirstSeenAt(new DateTimeImmutable($data['first_seen_at'] ?? 'now'));
        $device->setLastSeenAt(new DateTimeImmutable($data['last_seen_at'] ?? 'now'));
        $device->setMetadata($data['metadata'] ?? null);

        $this->entityManager->persist($device);
        $this->entityManager->flush();

        return $this->toCoreEntity($device);
    }

    public function findById(string $id): ?CoreDeviceInfo
    {
        $device = $this->entityManager->find($this->deviceInfoClass, $id);

        return $device ? $this->toCoreEntity($device) : null;
    }

    public function findByFingerprint(string $userId, string $fingerprint): ?CoreDeviceInfo
    {
        $device = $this->entityManager->getRepository($this->deviceInfoClass)
            ->findOneBy(['userId' => $userId, 'fingerprint' => $fingerprint]);

        return $device ? $this->toCoreEntity($device) : null;
    }

    public function findByUserId(string $userId): array
    {
        $devices = $this->entityManager->getRepository($this->deviceInfoClass)
            ->findBy(['userId' => $userId], ['lastSeenAt' => 'DESC']);

        return array_map(fn ($device) => $this->toCoreEntity($device), $devices);
    }

    public function update(string $id, array $data): CoreDeviceInfo
    {
        /** @var DeviceInfo|null $device */
        $device = $this->entityManager->find($this->deviceInfoClass, $id);
        if ($device === null) {
            throw new \RuntimeException("Device not found: $id");
        }

        if (isset($data['ip_address'])) {
            $device->setIpAddress($data['ip_address']);
        }
        if (isset($data['location'])) {
            $device->setLocation($data['location']);
        }
        if (isset($data['is_trusted'])) {
            $device->setIsTrusted($data['is_trusted']);
        }
        if (isset($data['last_seen_at'])) {
            $device->setLastSeenAt(new DateTimeImmutable($data['last_seen_at']));
        }

        $this->entityManager->flush();

        return $this->toCoreEntity($device);
    }

    public function delete(string $id): bool
    {
        $device = $this->entityManager->find($this->deviceInfoClass, $id);
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
            'id' => (string) $device->getId(),
            'user_id' => (string) $device->getUserId(),
            'fingerprint' => $device->getFingerprint(),
            'device_type' => $device->getDeviceType(),
            'browser' => $device->getBrowser(),
            'browser_version' => $device->getBrowserVersion(),
            'os' => $device->getOs(),
            'os_version' => $device->getOsVersion(),
            'ip_address' => $device->getIpAddress(),
            'location' => $device->getLocation(),
            'is_trusted' => $device->isTrusted(),
            'first_seen_at' => $device->getFirstSeenAt()->format('Y-m-d H:i:s'),
            'last_seen_at' => $device->getLastSeenAt()->format('Y-m-d H:i:s'),
            'metadata' => $device->getMetadata(),
        ]);
    }
}
