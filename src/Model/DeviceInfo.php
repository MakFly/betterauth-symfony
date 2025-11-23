<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'device_info')]
#[ORM\Index(columns: ['user_id'])]
#[ORM\Index(columns: ['fingerprint'])]
class DeviceInfo
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    public string $id;

    #[ORM\Column(type: 'string', length: 36)]
    public string $userId;

    #[ORM\Column(type: 'string', length: 64)]
    public string $fingerprint;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    public ?string $deviceType = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $browser = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    public ?string $browserVersion = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    public ?string $os = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    public ?string $osVersion = null;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    public ?string $ipAddress = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    public ?string $location = null;

    #[ORM\Column(type: 'boolean')]
    public bool $isTrusted = false;

    #[ORM\Column(type: 'datetime_immutable')]
    public DateTimeImmutable $firstSeenAt;

    #[ORM\Column(type: 'datetime_immutable')]
    public DateTimeImmutable $lastSeenAt;

    #[ORM\Column(type: 'json', nullable: true)]
    public ?array $metadata = null;
}
