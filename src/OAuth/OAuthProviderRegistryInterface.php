<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\OAuth;

use BetterAuth\Core\Interfaces\OAuthProviderInterface;

/**
 * Registry for OAuth provider implementations.
 *
 * Allows third-party bundles to register custom OAuth providers
 * via the 'better_auth.oauth_provider' service tag.
 */
interface OAuthProviderRegistryInterface
{
    /**
     * Register an OAuth provider under a given name.
     *
     * @param string $name The provider name (e.g., 'google', 'github')
     * @param OAuthProviderInterface $provider The provider implementation
     */
    public function register(string $name, OAuthProviderInterface $provider): void;

    /**
     * Get a registered OAuth provider by name.
     *
     * @param string $name The provider name
     *
     * @throws \InvalidArgumentException If the provider is not registered
     */
    public function get(string $name): OAuthProviderInterface;

    /**
     * Check if a provider is registered under the given name.
     *
     * @param string $name The provider name
     */
    public function has(string $name): bool;

    /**
     * Get the list of all registered provider names.
     *
     * @return string[]
     */
    public function getAvailableProviders(): array;
}
