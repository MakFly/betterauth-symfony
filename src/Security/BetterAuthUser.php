<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Security;

use BetterAuth\Core\Entities\User as BetterAuthCoreUser;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Wrapper pour BetterAuth\Core\Entities\User
 * Implémente UserInterface de Symfony
 */
class BetterAuthUser implements UserInterface
{
    public function __construct(
        private readonly BetterAuthCoreUser $user
    ) {
    }

    /**
     * {@inheritdoc}
     *
     * @return non-empty-string
     */
    public function getUserIdentifier(): string
    {
        $id = (string) $this->user->getId();
        assert($id !== '');
        return $id;
    }

    /**
     * {@inheritdoc}
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
        // Rien à effacer (stateless)
    }

    /**
     * Récupérer l'utilisateur BetterAuth original
     */
    public function getBetterAuthUser(): BetterAuthCoreUser
    {
        return $this->user;
    }

    /**
     * Forward property access to BetterAuth user
     */
    public function __get(string $name): mixed
    {
        return $this->user->$name;
    }

    /**
     * Forward method calls to BetterAuth user
     *
     * @param array<int, mixed> $args
     */
    public function __call(string $method, array $args): mixed
    {
        return $this->user->$method(...$args);
    }
}
