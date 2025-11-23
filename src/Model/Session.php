<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Model;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Base Session entity for BetterAuth - Mapped Superclass.
 * Extend this class in your application if you need custom session fields.
 */
#[ORM\MappedSuperclass]
abstract class Session
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $token;

    #[ORM\Column(name: 'user_id', type: Types::STRING, length: 36)]
    protected string $userId;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'ip_address', type: Types::STRING, length: 45)]
    protected string $ipAddress;

    #[ORM\Column(name: 'user_agent', type: Types::STRING, length: 500)]
    protected string $userAgent;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    protected ?array $metadata = null;

    #[ORM\Column(name: 'active_organization_id', type: Types::STRING, length: 36, nullable: true)]
    protected ?string $activeOrganizationId = null;

    #[ORM\Column(name: 'active_team_id', type: Types::STRING, length: 36, nullable: true)]
    protected ?string $activeTeamId = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;

        return $this;
    }

    public function getUserId(): string
    {
        return $this->userId;
    }

    public function setUserId(string $userId): self
    {
        $this->userId = $userId;

        return $this;
    }

    public function getExpiresAt(): DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(DateTimeImmutable $expiresAt): self
    {
        $this->expiresAt = $expiresAt;

        return $this;
    }

    public function getIpAddress(): string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): self
    {
        $this->ipAddress = $ipAddress;

        return $this;
    }

    public function getUserAgent(): string
    {
        return $this->userAgent;
    }

    public function setUserAgent(string $userAgent): self
    {
        $this->userAgent = $userAgent;

        return $this;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): self
    {
        $this->metadata = $metadata;

        return $this;
    }

    public function getActiveOrganizationId(): ?string
    {
        return $this->activeOrganizationId;
    }

    public function setActiveOrganizationId(?string $activeOrganizationId): self
    {
        $this->activeOrganizationId = $activeOrganizationId;

        return $this;
    }

    public function getActiveTeamId(): ?string
    {
        return $this->activeTeamId;
    }

    public function setActiveTeamId(?string $activeTeamId): self
    {
        $this->activeTeamId = $activeTeamId;

        return $this;
    }
}
