<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Security;

use BetterAuth\Contracts\AuthUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Adapts a BetterAuth AuthUserInterface to Symfony's UserInterface.
 *
 * This class bridges the framework-agnostic core contracts with the
 * Symfony Security component, keeping betterauth-core free of Symfony dependencies.
 */
final class UserAdapter implements UserInterface
{
    public function __construct(
        private readonly AuthUserInterface $user,
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        $identifier = $this->user->getUserIdentifier();
        assert($identifier !== '');
        return $identifier;
    }

    /**
     * {@inheritdoc}
     *
     * @return string[]
     */
    public function getRoles(): array
    {
        return $this->user->getRoles();
    }

    /**
     * {@inheritdoc}
     */
    public function eraseCredentials(): void
    {
        $this->user->eraseCredentials();
    }

    /**
     * Returns the wrapped AuthUserInterface instance.
     */
    public function getAuthUser(): AuthUserInterface
    {
        return $this->user;
    }
}
