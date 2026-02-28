<?php

declare(strict_types=1);

namespace BetterAuth\Tests\Fixtures;

use BetterAuth\Core\Entities\User;
use DateTimeImmutable;

/**
 * Concrete User implementation for testing purposes.
 */
class TestUser extends User
{
    protected string|int $id;

    public function getId(): string|int|null
    {
        return $this->id ?? null;
    }

    public function setId(string|int $id): static
    {
        $this->id = $id;

        return $this;
    }

    /**
     * Create a TestUser from an array of data.
     */
    public static function fromArray(array $data): self
    {
        $user = new self();

        if (isset($data['id'])) {
            $user->setId($data['id']);
        }
        if (isset($data['email'])) {
            $user->setEmail($data['email']);
        }
        if (isset($data['password']) || isset($data['password_hash'])) {
            $user->setPassword($data['password'] ?? $data['password_hash']);
        }
        if (isset($data['username'])) {
            $user->setUsername($data['username']);
        }
        if (isset($data['avatar'])) {
            $user->setAvatar($data['avatar']);
        }
        if (isset($data['email_verified'])) {
            $user->setEmailVerified((bool) $data['email_verified']);
        }
        if (isset($data['email_verified_at'])) {
            $emailVerifiedAt = $data['email_verified_at'] instanceof DateTimeImmutable
                ? $data['email_verified_at']
                : new DateTimeImmutable($data['email_verified_at']);
            $user->setEmailVerifiedAt($emailVerifiedAt);
        }
        if (isset($data['created_at'])) {
            $createdAt = $data['created_at'] instanceof DateTimeImmutable
                ? $data['created_at']
                : new DateTimeImmutable($data['created_at']);
            $user->setCreatedAt($createdAt);
        }
        if (isset($data['updated_at'])) {
            $updatedAt = $data['updated_at'] instanceof DateTimeImmutable
                ? $data['updated_at']
                : new DateTimeImmutable($data['updated_at']);
            $user->setUpdatedAt($updatedAt);
        }
        if (isset($data['roles'])) {
            $user->setRoles($data['roles']);
        }
        if (isset($data['metadata'])) {
            $user->setMetadata($data['metadata']);
        }

        return $user;
    }
}
