<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Security;

use BetterAuth\Core\Interfaces\UserRepositoryInterface;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

/**
 * UserProvider pour Symfony Security
 *
 * Usage dans security.yaml:
 * security:
 *     providers:
 *         better_auth:
 *             id: BetterAuth\Symfony\Security\BetterAuthUserProvider
 */
class BetterAuthUserProvider implements UserProviderInterface
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof BetterAuthUser) {
            throw new \InvalidArgumentException(sprintf('Instances of "%s" are not supported.', get_class($user)));
        }

        $betterAuthUser = $user->getBetterAuthUser();
        $freshUser = $this->userRepository->findById($betterAuthUser->id);

        if (!$freshUser) {
            throw new UserNotFoundException('User not found');
        }

        return new BetterAuthUser($freshUser);
    }

    /**
     * {@inheritdoc}
     */
    public function supportsClass(string $class): bool
    {
        return BetterAuthUser::class === $class || is_subclass_of($class, BetterAuthUser::class);
    }

    /**
     * {@inheritdoc}
     */
    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->userRepository->findById($identifier);

        if (!$user) {
            throw new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
        }

        return new BetterAuthUser($user);
    }
}
