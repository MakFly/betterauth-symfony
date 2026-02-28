<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Storage\Doctrine;

use BetterAuth\Core\Entities\SimpleUser;
use BetterAuth\Core\Entities\User;
use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use BetterAuth\Symfony\Model\User as UserModel;
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
    /** @var class-string<UserModel> */
    private string $userClass;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserIdConverter $idConverter,
        string $userClass = User::class
    ) {
        /** @var class-string<UserModel> $userClass */
        $this->userClass = $userClass;
    }

    public function findById(string $id): ?User
    {
        /** @var UserModel|null $doctrineUser */
        $doctrineUser = $this->entityManager->getRepository($this->userClass)->find($this->idConverter->toDatabaseId($id));

        if ($doctrineUser === null) {
            return null;
        }

        return $this->toEntity($doctrineUser);
    }

    public function findByEmail(string $email): ?User
    {
        /** @var UserModel|null $doctrineUser */
        $doctrineUser = $this->entityManager->getRepository($this->userClass)
            ->findOneBy(['email' => $email]);

        if ($doctrineUser === null) {
            return null;
        }

        return $this->toEntity($doctrineUser);
    }

    public function findByProvider(string $provider, string $providerId): ?User
    {
        // Validate provider name to prevent injection in LIKE pattern
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $provider)) {
            throw new \InvalidArgumentException('Invalid provider name');
        }

        // Use a parameterized DQL query instead of findAll() to avoid loading all users
        // The LIKE pattern matches the JSON structure stored in the metadata column
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('u')
            ->from($this->userClass, 'u')
            ->where("u.metadata LIKE :pattern")
            ->setParameter('pattern', '%"' . $provider . '":{"id":"' . $providerId . '"%')
            ->setMaxResults(1);

        /** @var UserModel|null $doctrineUser */
        $doctrineUser = $qb->getQuery()->getOneOrNullResult();

        if ($doctrineUser === null) {
            return null;
        }

        return $this->toEntity($doctrineUser);
    }

    /**
     * @param array<string, mixed> $data
     */
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
        if (method_exists($doctrineUser, 'setUsername')) {
            $doctrineUser->setUsername($data['username'] ?? null);
        }
        if (method_exists($doctrineUser, 'setAvatar')) {
            $doctrineUser->setAvatar($data['avatar'] ?? null);
        }
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

    /**
     * @param array<string, mixed> $data
     */
    public function update(string $id, array $data): User
    {
        /** @var UserModel|null $doctrineUser */
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
        if (isset($data['username']) && method_exists($doctrineUser, 'setUsername')) {
            $doctrineUser->setUsername($data['username']);
        }
        if (isset($data['avatar']) && method_exists($doctrineUser, 'setAvatar')) {
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
        /** @var UserModel|null $doctrineUser */
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
        /** @var UserModel|null $doctrineUser */
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

    /** @param UserModel $doctrineUser */
    private function toEntity(object $doctrineUser): User
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
