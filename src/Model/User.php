<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Model;

use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Serializer\Annotation\Ignore;

/**
 * Base User entity for BetterAuth - Mapped Superclass.
 *
 * This class defines ONLY the essential fields required for authentication.
 * Optional fields (name, avatar) are available via UserProfileTrait.
 *
 * This class does NOT define the ID - the child class must define it.
 * This allows flexibility between UUID (string) and INT (integer) strategies.
 *
 * Implements Symfony Security interfaces for seamless integration.
 *
 * @example UUID strategy with minimal fields:
 * ```php
 * #[ORM\Entity]
 * class User extends BaseUser {
 *     #[ORM\Id]
 *     #[ORM\Column(type: Types::STRING, length: 36)]
 *     protected string $id;
 * }
 * ```
 *
 * @example UUID strategy with profile fields (name, avatar):
 * ```php
 * #[ORM\Entity]
 * class User extends BaseUser {
 *     use UserProfileTrait;
 *
 *     #[ORM\Id]
 *     #[ORM\Column(type: Types::STRING, length: 36)]
 *     protected string $id;
 * }
 * ```
 */
#[ORM\MappedSuperclass]
abstract class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    /**
     * List of optional fields that can be excluded during installation.
     * These fields are provided via UserProfileTrait.
     */
    public const OPTIONAL_FIELDS = ['name', 'avatar'];

    #[ORM\Column(type: Types::STRING, length: 255, unique: true)]
    protected string $email;

    #[ORM\Column(type: Types::STRING, length: 255, nullable: true)]
    protected ?string $password = null;

    /** @var string[] */
    #[ORM\Column(type: Types::JSON)]
    protected array $roles = ['ROLE_USER'];

    #[ORM\Column(name: 'email_verified', type: Types::BOOLEAN)]
    protected bool $emailVerified = false;

    #[ORM\Column(name: 'email_verified_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    protected ?DateTimeImmutable $emailVerifiedAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'updated_at', type: Types::DATETIME_IMMUTABLE)]
    protected DateTimeImmutable $updatedAt;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    protected ?array $metadata = null;

    public function __construct()
    {
        $this->createdAt = new DateTimeImmutable();
        $this->updatedAt = new DateTimeImmutable();
    }

    /**
     * Get the user ID.
     * Must be implemented by child class based on ID strategy.
     */
    abstract public function getId(): string|int|null;

    /**
     * Set the user ID.
     * Must be implemented by child class based on ID strategy.
     */
    abstract public function setId(string|int $id): static;

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     * @Ignore()
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * The public representation of the user (e.g. a username, an email address, etc.)
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return $this->email;
    }

    /**
     * @see UserInterface
     *
     * @return string[]
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // Guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    /**
     * @param string[] $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function isEmailVerified(): bool
    {
        return $this->emailVerified;
    }

    public function setEmailVerified(bool $emailVerified): static
    {
        $this->emailVerified = $emailVerified;

        return $this;
    }

    public function getEmailVerifiedAt(): ?DateTimeImmutable
    {
        return $this->emailVerifiedAt;
    }

    public function setEmailVerifiedAt(?DateTimeImmutable $emailVerifiedAt): static
    {
        $this->emailVerifiedAt = $emailVerifiedAt;

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

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

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

    /**
     * Check if user has a password (for Magic Link users who might not have one).
     */
    public function hasPassword(): bool
    {
        return $this->password !== null && $this->password !== '';
    }

    /**
     * Get name - returns null by default.
     * Override this method or use UserProfileTrait for name support.
     */
    public function getName(): ?string
    {
        return null;
    }

    /**
     * Get avatar - returns null by default.
     * Override this method or use UserProfileTrait for avatar support.
     */
    public function getAvatar(): ?string
    {
        return null;
    }

    /**
     * Magic getter to allow read access to protected properties.
     *
     * This enables convenient property access like $user->email
     * while maintaining encapsulation (properties remain protected).
     *
     * @param string $name Property name
     *
     * @return mixed Property value
     *
     * @throws \InvalidArgumentException If property doesn't exist
     */
    public function __get(string $name): mixed
    {
        $allowedProperties = [
            'id', 'email', 'password', 'roles', 'name', 'avatar',
            'emailVerified', 'emailVerifiedAt', 'createdAt', 'updatedAt', 'metadata',
        ];

        if (in_array($name, $allowedProperties, true)) {
            // Use getter if available (for overridden properties like name/avatar)
            $getter = 'get' . ucfirst($name);
            if (method_exists($this, $getter)) {
                return $this->$getter();
            }
            // Special case for boolean emailVerified
            if ($name === 'emailVerified' && method_exists($this, 'isEmailVerified')) {
                return $this->isEmailVerified();
            }

            return $this->$name ?? null;
        }

        throw new \InvalidArgumentException(sprintf('Property "%s" does not exist on %s', $name, static::class));
    }

    /**
     * Magic isset to support property_exists checks.
     *
     * @param string $name Property name
     */
    public function __isset(string $name): bool
    {
        $allowedProperties = [
            'id', 'email', 'password', 'roles', 'name', 'avatar',
            'emailVerified', 'emailVerifiedAt', 'createdAt', 'updatedAt', 'metadata',
        ];

        if (!in_array($name, $allowedProperties, true)) {
            return false;
        }

        // Use getter if available
        $getter = 'get' . ucfirst($name);
        if (method_exists($this, $getter)) {
            return $this->$getter() !== null;
        }

        return isset($this->$name);
    }
}
