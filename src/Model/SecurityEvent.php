<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Base SecurityEvent entity for BetterAuth - Mapped Superclass.
 *
 * Stores security audit events for users.
 * Extend this class in your application to define the ID and userId types.
 */
#[ORM\MappedSuperclass]
abstract class SecurityEvent
{
    #[ORM\Column(type: 'string', length: 50)]
    protected string $eventType;

    #[ORM\Column(type: 'string', length: 20)]
    protected string $severity;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    protected ?string $ipAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $userAgent = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $location = null;

    #[ORM\Column(type: 'datetime_immutable')]
    protected DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'json', nullable: true)]
    protected ?array $details = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    abstract public function getId(): string|int|null;

    abstract public function setId(string|int $id): static;

    abstract public function getUserId(): string|int;

    abstract public function setUserId(string|int $userId): static;

    public function getEventType(): string
    {
        return $this->eventType;
    }

    public function setEventType(string $eventType): static
    {
        $this->eventType = $eventType;

        return $this;
    }

    public function getSeverity(): string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): static
    {
        $this->severity = $severity;

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

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): static
    {
        $this->userAgent = $userAgent;

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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getDetails(): ?array
    {
        return $this->details;
    }

    public function setDetails(?array $details): static
    {
        $this->details = $details;

        return $this;
    }
}
