<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\SimpleUser;
use BetterAuth\Core\Entities\User;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use BetterAuth\Symfony\Service\UserIdConverter;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Doctrine implementation of UserRepositoryInterface.
 *
 * This repository is final to ensure consistent user persistence behavior.
 */
final class DoctrineUserRepository implements UserRepositoryInterface
{
    private string $userClass;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserIdConverter $idConverter,
        string $userClass = User::class
    ) {
        $this->userClass = $userClass;
    }

    public function findById(string $id): ?User
    {
        $doctrineUser = $this->entityManager->getRepository($this->userClass)->find($this->idConverter->toDatabaseId($id));

        if ($doctrineUser === null) {
            return null;
        }

        return $this->toEntity($doctrineUser);
    }

    public function findByEmail(string $email): ?User
    {
        $doctrineUser = $this->entityManager->getRepository($this->userClass)
            ->findOneBy(['email' => $email]);

        if ($doctrineUser === null) {
            return null;
        }

        return $this->toEntity($doctrineUser);
    }

    public function findByProvider(string $provider, string $providerId): ?User
    {
        // For now, we'll use metadata to store provider info
        // In a production app, you might want a separate OAuthAccount table
        $users = $this->entityManager->getRepository($this->userClass)->findAll();

        foreach ($users as $user) {
            $metadata = $user->getMetadata();
            if (
                isset($metadata['oauth'][$provider]) &&
                $metadata['oauth'][$provider]['id'] === $providerId
            ) {
                return $this->toEntity($user);
            }
        }

        return null;
    }

    public function create(array $data): User
    {
        $doctrineUser = new ($this->userClass)();

        // Only set ID for UUID-based entities (when ID is provided)
        // For INT-based entities, Doctrine auto-generates the ID
        if ($this->idConverter->usesStringId() && isset($data['id'])) {
            $doctrineUser->setId($data['id']);
        }

        $doctrineUser->setEmail($data['email']);
        $doctrineUser->setPassword($data['password'] ?? null);
        $doctrineUser->setUsername($data['username'] ?? null);
        $doctrineUser->setAvatar($data['avatar'] ?? null);
        $doctrineUser->setEmailVerified($data['email_verified'] ?? false);
        $doctrineUser->setMetadata($data['metadata'] ?? null);

        if (isset($data['email_verified_at'])) {
            $doctrineUser->setEmailVerifiedAt(
                $data['email_verified_at'] instanceof DateTimeImmutable
                    ? $data['email_verified_at']
                    : new DateTimeImmutable($data['email_verified_at'])
            );
        }

        $this->entityManager->persist($doctrineUser);
        $this->entityManager->flush();

        return $this->toEntity($doctrineUser);
    }

    public function update(string $id, array $data): User
    {
        $doctrineUser = $this->entityManager->getRepository($this->userClass)->find($this->idConverter->toDatabaseId($id));

        if ($doctrineUser === null) {
            throw new \RuntimeException("User not found: $id");
        }

        if (isset($data['email'])) {
            $doctrineUser->setEmail($data['email']);
        }
        if (isset($data['password'])) {
            $doctrineUser->setPassword($data['password']);
        }
        if (isset($data['username'])) {
            $doctrineUser->setUsername($data['username']);
        }
        if (isset($data['avatar'])) {
            $doctrineUser->setAvatar($data['avatar']);
        }
        if (isset($data['email_verified'])) {
            $doctrineUser->setEmailVerified($data['email_verified']);
        }
        if (isset($data['email_verified_at'])) {
            $doctrineUser->setEmailVerifiedAt(
                $data['email_verified_at'] instanceof DateTimeImmutable
                    ? $data['email_verified_at']
                    : new DateTimeImmutable($data['email_verified_at'])
            );
        }
        if (isset($data['metadata'])) {
            $doctrineUser->setMetadata($data['metadata']);
        }

        $doctrineUser->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return $this->toEntity($doctrineUser);
    }

    public function delete(string $id): bool
    {
        $doctrineUser = $this->entityManager->getRepository($this->userClass)->find($this->idConverter->toDatabaseId($id));

        if ($doctrineUser === null) {
            return false;
        }

        $this->entityManager->remove($doctrineUser);
        $this->entityManager->flush();

        return true;
    }

    public function verifyEmail(string $id): bool
    {
        $doctrineUser = $this->entityManager->getRepository($this->userClass)->find($this->idConverter->toDatabaseId($id));

        if ($doctrineUser === null) {
            return false;
        }

        $doctrineUser->setEmailVerified(true);
        $doctrineUser->setEmailVerifiedAt(new DateTimeImmutable());
        $doctrineUser->setUpdatedAt(new DateTimeImmutable());

        $this->entityManager->flush();

        return true;
    }

    public function generateId(): ?string
    {
        return $this->idConverter->generateId();
    }

    private function toEntity($doctrineUser): User
    {
        return SimpleUser::fromArray([
            'id' => $this->idConverter->toAuthId($doctrineUser->getId()), // Convert to string for Core Entity
            'email' => $doctrineUser->getEmail(),
            'password' => $doctrineUser->getPassword(),
            'username' => $doctrineUser->getUsername(),
            'avatar' => $doctrineUser->getAvatar(),
            'roles' => $doctrineUser->getRoles(),
            'email_verified' => $doctrineUser->isEmailVerified(),
            'email_verified_at' => $doctrineUser->getEmailVerifiedAt()?->format('Y-m-d H:i:s'),
            'created_at' => $doctrineUser->getCreatedAt()->format('Y-m-d H:i:s'),
            'updated_at' => $doctrineUser->getUpdatedAt()->format('Y-m-d H:i:s'),
            'metadata' => $doctrineUser->getMetadata(),
        ]);
    }
}
