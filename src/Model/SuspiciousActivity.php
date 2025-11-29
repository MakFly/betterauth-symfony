<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Base SuspiciousActivity entity for BetterAuth - Mapped Superclass.
 *
 * Stores suspicious activity detection for users.
 * Extend this class in your application to define the ID and userId types.
 */
#[ORM\MappedSuperclass]
abstract class SuspiciousActivity
{
    #[ORM\Column(type: 'string', length: 50)]
    protected string $activityType;

    #[ORM\Column(type: 'string', length: 20)]
    protected string $riskLevel;

    #[ORM\Column(type: 'string', length: 45, nullable: true)]
    protected ?string $ipAddress = null;

    #[ORM\Column(type: 'text', nullable: true)]
    protected ?string $userAgent = null;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $location = null;

    #[ORM\Column(type: 'datetime_immutable')]
    protected DateTimeImmutable $detectedAt;

    #[ORM\Column(type: 'string', length: 20)]
    protected string $status = 'pending';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    protected ?DateTimeImmutable $resolvedAt = null;

    #[ORM\Column(type: 'json', nullable: true)]
    protected ?array $details = null;

    public function __construct()
    {
        $this->detectedAt = new DateTimeImmutable();
    }

    abstract public function getId(): string|int|null;

    abstract public function setId(string|int $id): static;

    abstract public function getUserId(): string|int;

    abstract public function setUserId(string|int $userId): static;

    public function getActivityType(): string
    {
        return $this->activityType;
    }

    public function setActivityType(string $activityType): static
    {
        $this->activityType = $activityType;

        return $this;
    }

    public function getRiskLevel(): string
    {
        return $this->riskLevel;
    }

    public function setRiskLevel(string $riskLevel): static
    {
        $this->riskLevel = $riskLevel;

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

    public function getDetectedAt(): DateTimeImmutable
    {
        return $this->detectedAt;
    }

    public function setDetectedAt(DateTimeImmutable $detectedAt): static
    {
        $this->detectedAt = $detectedAt;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getResolvedAt(): ?DateTimeImmutable
    {
        return $this->resolvedAt;
    }

    public function setResolvedAt(?DateTimeImmutable $resolvedAt): static
    {
        $this->resolvedAt = $resolvedAt;

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
