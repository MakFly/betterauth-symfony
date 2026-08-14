<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Contracts;

/**
 * Interface for users that store a hashed password.
 *
 * Replaces Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface
 * in the core library.
 */
interface PasswordHolderInterface
{
    /**
     * Returns the hashed password for this user.
     *
     * Returns null for passwordless users (for example, magic-link users).
     */
    public function getPassword(): ?string;
}
