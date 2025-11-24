<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DateTimeImmutable;

/**
 * Session entity - Custom implementation with INT user_id.
 *
 * Sessions use token as primary key (string).
 * User ID is stored as INTEGER for compatibility with INT User entity.
 */
#[ORM\Entity]
#[ORM\Table(name: 'sessions')]
class Session
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $token;

    #[ORM\Column(name: 'user_id', type: Types::INTEGER)]
    private int $userId;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'ip_address', type: Types::STRING, length: 45)]
    private string $ipAddress;

    #[ORM\Column(name: 'user_agent', type: Types::STRING, length: 500)]
    private string $userAgent;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column(name: 'active_organization_id', type: Types::INTEGER, nullable: true)]
    private ?int $activeOrganizationId = null;

    #[ORM\Column(name: 'active_team_id', type: Types::INTEGER, nullable: true)]
    private ?int $activeTeamId = null;

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

    public function getUserId(): int
    {
        return $this->userId;
    }

    public function setUserId(int $userId): self
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

    public function getActiveOrganizationId(): ?int
    {
        return $this->activeOrganizationId;
    }

    public function setActiveOrganizationId(?int $activeOrganizationId): self
    {
        $this->activeOrganizationId = $activeOrganizationId;
        return $this;
    }

    public function getActiveTeamId(): ?int
    {
        return $this->activeTeamId;
    }

    public function setActiveTeamId(?int $activeTeamId): self
    {
        $this->activeTeamId = $activeTeamId;
        return $this;
    }
}
