<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Base SessionActivity entity for BetterAuth - Mapped Superclass.
 *
 * Stores session activity tracking.
 * Extend this class in your application to define the ID and sessionId types.
 */
#[ORM\MappedSuperclass]
abstract class SessionActivity
{
    #[ORM\Column(type: 'string', length: 50)]
    protected string $action;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    protected ?string $ipAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $userAgent = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $location = null;

    #[ORM\Column(type: 'datetime_immutable')]
    protected DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'json', nullable: true)]
    protected ?array $metadata = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
    }

    abstract public function getId(): string|int|null;

    abstract public function setId(string|int $id): static;

    abstract public function getSessionId(): string;

    abstract public function setSessionId(string $sessionId): static;

    public function getAction(): string
    {
        return $this->action;
    }

    public function setAction(string $action): static
    {
        $this->action = $action;

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
