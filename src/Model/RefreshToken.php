<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Model;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

/**
 * Base RefreshToken entity for BetterAuth - Mapped Superclass.
 * Extend this class in your application if you need custom refresh token fields.
 */
#[ORM\MappedSuperclass]
abstract class RefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 255)]
    protected string $token;

    #[ORM\Column(name: 'user_id', type: Types::STRING, length: 36)]
    protected string $userId;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::BOOLEAN)]
    protected bool $revoked = false;

    #[ORM\Column(name: 'replaced_by', type: Types::STRING, length: 255, nullable: true)]
    protected ?string $replacedBy = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
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

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): self
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function isRevoked(): bool
    {
        return $this->revoked;
    }

    public function setRevoked(bool $revoked): self
    {
        $this->revoked = $revoked;

        return $this;
    }

    public function getReplacedBy(): ?string
    {
        return $this->replacedBy;
    }

    public function setReplacedBy(?string $replacedBy): self
    {
        $this->replacedBy = $replacedBy;

        return $this;
    }
}
