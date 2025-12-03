<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Security;

use BetterAuth\Symfony\Model\User as BetterAuthModelUser;
use BetterAuth\Symfony\Service\UserIdConverter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * UserProvider pour Symfony Security.
 *
 * Returns the actual Doctrine User entity (App\Entity\User) instead of a wrapper,
 * allowing seamless integration with Symfony Security and API Platform.
 *
 * Usage dans security.yaml:
 * security:
 *     providers:
 *         better_auth:
 *             id: BetterAuth\Symfony\Security\BetterAuthUserProvider
 */
class BetterAuthUserProvider implements UserProviderInterface
{
    private string $userClass;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserIdConverter $idConverter,
        string $userClass = BetterAuthModelUser::class,
    ) {
        $this->userClass = $userClass;
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof BetterAuthModelUser) {
            throw new \InvalidArgumentException(sprintf(
                'Instances of "%s" are not supported. Expected instance of "%s".',
                get_class($user),
                BetterAuthModelUser::class
            ));
        }

        $freshUser = $this->entityManager
            ->getRepository($this->userClass)
            ->find($user->getId());

        if (!$freshUser) {
            throw new UserNotFoundException('User not found');
        }

        return $freshUser;
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass(string $class): bool
    {
        return $class === $this->userClass || is_subclass_of($class, BetterAuthModelUser::class);
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->entityManager
            ->getRepository($this->userClass)
            ->find($this->idConverter->toDatabaseId($identifier));

        if (!$user) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        return $user;
    }
}
