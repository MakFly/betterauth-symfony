<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;

/**
 * Base AccountLink entity for BetterAuth - Mapped Superclass.
 *
 * Stores OAuth/social provider links for users.
 * Extend this class in your application to define the ID and userId types.
 *
 * @example UUID strategy:
 * ```php
 * #[ORM\Entity]
 * #[ORM\Table(name: 'account_links')]
 * class AccountLink extends BaseAccountLink {
 *     #[ORM\Id]
 *     #[ORM\Column(type: 'string', length: 36)]
 *     protected string $id;
 *
 *     #[ORM\Column(type: 'string', length: 36)]
 *     protected string $userId;
 * }
 * ```
 *
 * @example INT strategy:
 * ```php
 * #[ORM\Entity]
 * #[ORM\Table(name: 'account_links')]
 * class AccountLink extends BaseAccountLink {
 *     #[ORM\Id]
 *     #[ORM\GeneratedValue]
 *     #[ORM\Column(type: 'integer')]
 *     protected ?int $id = null;
 *
 *     #[ORM\Column(type: 'integer')]
 *     protected int $userId;
 * }
 * ```
 */
#[ORM\MappedSuperclass]
abstract class AccountLink
{
    #[ORM\Column(type: 'string', length: 50)]
    protected string $provider;

    #[ORM\Column(type: 'string', length: 255)]
    protected string $providerId;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    protected ?string $providerEmail = null;

    #[ORM\Column(type: 'boolean')]
    protected bool $isPrimary = false;

    #[ORM\Column(type: 'string', length: 20)]
    protected string $status = 'verified';

    #[ORM\Column(type: 'datetime_immutable')]
    protected DateTimeImmutable $linkedAt;

    #[ORM\Column(type: 'json', nullable: true)]
    protected ?array $metadata = null;

    public function __construct()
    {
        $this->linkedAt = new DateTimeImmutable();
    }

    /**
     * Get the entity ID.
     */
    abstract public function getId(): string|int|null;

    /**
     * Set the entity ID.
     */
    abstract public function setId(string|int $id): static;

    /**
     * Get the user ID.
     */
    abstract public function getUserId(): string|int;

    /**
     * Set the user ID.
     */
    abstract public function setUserId(string|int $userId): static;

    public function getProvider(): string
    {
        return $this->provider;
    }

    public function setProvider(string $provider): static
    {
        $this->provider = $provider;

        return $this;
    }

    public function getProviderId(): string
    {
        return $this->providerId;
    }

    public function setProviderId(string $providerId): static
    {
        $this->providerId = $providerId;

        return $this;
    }

    public function getProviderEmail(): ?string
    {
        return $this->providerEmail;
    }

    public function setProviderEmail(?string $providerEmail): static
    {
        $this->providerEmail = $providerEmail;

        return $this;
    }

    public function isPrimary(): bool
    {
        return $this->isPrimary;
    }

    public function setIsPrimary(bool $isPrimary): static
    {
        $this->isPrimary = $isPrimary;

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

    public function getLinkedAt(): DateTimeImmutable
    {
        return $this->linkedAt;
    }

    public function setLinkedAt(DateTimeImmutable $linkedAt): static
    {
        $this->linkedAt = $linkedAt;

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
