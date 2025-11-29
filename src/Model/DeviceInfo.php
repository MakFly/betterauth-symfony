<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Base DeviceInfo entity for BetterAuth - Mapped Superclass.
 *
 * Stores trusted device information for users.
 * Extend this class in your application to define the ID and userId types.
 */
#[ORM\MappedSuperclass]
abstract class DeviceInfo
{
    #[ORM\Column(type: 'string', length: 64)]
    protected string $fingerprint;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    protected ?string $deviceType = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    protected ?string $browser = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    protected ?string $browserVersion = null;

    #[ORM\Column(type: 'string', length: 50, nullable: true)]
    protected ?string $os = null;

    #[ORM\Column(type: 'string', length: 20, nullable: true)]
    protected ?string $osVersion = null;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    protected ?string $ipAddress = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $location = null;

    #[ORM\Column(type: 'boolean')]
    protected bool $isTrusted = false;

    #[ORM\Column(type: 'datetime_immutable')]
    protected DateTimeImmutable $firstSeenAt;

    #[ORM\Column(type: 'datetime_immutable')]
    protected DateTimeImmutable $lastSeenAt;

    #[ORM\Column(type: 'json', nullable: true)]
    protected ?array $metadata = null;

    public function __construct()
    {
        $this->firstSeenAt = new DateTimeImmutable();
        $this->lastSeenAt = new DateTimeImmutable();
    }

    abstract public function getId(): string|int|null;

    abstract public function setId(string|int $id): static;

    abstract public function getUserId(): string|int;

    abstract public function setUserId(string|int $userId): static;

    public function getFingerprint(): string
    {
        return $this->fingerprint;
    }

    public function setFingerprint(string $fingerprint): static
    {
        $this->fingerprint = $fingerprint;

        return $this;
    }

    public function getDeviceType(): ?string
    {
        return $this->deviceType;
    }

    public function setDeviceType(?string $deviceType): static
    {
        $this->deviceType = $deviceType;

        return $this;
    }

    public function getBrowser(): ?string
    {
        return $this->browser;
    }

    public function setBrowser(?string $browser): static
    {
        $this->browser = $browser;

        return $this;
    }

    public function getBrowserVersion(): ?string
    {
        return $this->browserVersion;
    }

    public function setBrowserVersion(?string $browserVersion): static
    {
        $this->browserVersion = $browserVersion;

        return $this;
    }

    public function getOs(): ?string
    {
        return $this->os;
    }

    public function setOs(?string $os): static
    {
        $this->os = $os;

        return $this;
    }

    public function getOsVersion(): ?string
    {
        return $this->osVersion;
    }

    public function setOsVersion(?string $osVersion): static
    {
        $this->osVersion = $osVersion;

        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;

        return $this;
    }

    public function isTrusted(): bool
    {
        return $this->isTrusted;
    }

    public function setIsTrusted(bool $isTrusted): static
    {
        $this->isTrusted = $isTrusted;

        return $this;
    }

    public function getFirstSeenAt(): DateTimeImmutable
    {
        return $this->firstSeenAt;
    }

    public function setFirstSeenAt(DateTimeImmutable $firstSeenAt): static
    {
        $this->firstSeenAt = $firstSeenAt;

        return $this;
    }

    public function getLastSeenAt(): DateTimeImmutable
    {
        return $this->lastSeenAt;
    }

    public function setLastSeenAt(DateTimeImmutable $lastSeenAt): static
    {
        $this->lastSeenAt = $lastSeenAt;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;

        return $this;
    }
}
