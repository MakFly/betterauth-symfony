<?php

declare(strict_types=1);

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use DateTimeImmutable;

/**
 * RefreshToken entity - Custom implementation with INT user_id.
 *
 * Refresh tokens use token as primary key (string).
 * User ID is stored as INTEGER for compatibility with INT User entity.
 */
#[ORM\Entity]
#[ORM\Table(name: 'refresh_tokens')]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\Column(type: Types::STRING, length: 255)]
    private string $token;

    #[ORM\Column(name: 'user_id', type: Types::INTEGER)]
    private int $userId;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::BOOLEAN)]
    private bool $revoked = false;

    #[ORM\Column(name: 'replaced_by', type: Types::STRING, length: 255, nullable: true)]
    private ?string $replacedBy = null;

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
