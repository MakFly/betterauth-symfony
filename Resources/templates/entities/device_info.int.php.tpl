<?php

declare(strict_types=1);

namespace App\Entity;

use BetterAuth\Symfony\Model\DeviceInfo as BaseDeviceInfo;
use Doctrine\ORM\Mapping as ORM;

/**
 * DeviceInfo entity with INT IDs.
 */
#[ORM\Entity]
#[ORM\Table(name: 'device_info')]
#[ORM\Index(columns: ['user_id'])]
#[ORM\Index(columns: ['fingerprint'])]
class DeviceInfo extends BaseDeviceInfo
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(type: 'integer')]
    protected ?int $id = null;

    #[ORM\Column(type: 'integer')]
    protected int $userId;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(string|int $id): static
    {
        $this->id = (int) $id;

        return $this;
    }

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(string|int $userId): static
    {
        $this->userId = (int) $userId;

        return $this;
    }
}
