<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\OAuth;

use BetterAuth\Core\Interfaces\OAuthProviderInterface;

/**
 * Default array-backed registry for OAuth providers.
 *
 * Providers are registered at container compile time via the
 * OAuthProviderPass compiler pass using the 'better_auth.oauth_provider' tag.
 */
final class OAuthProviderRegistry implements OAuthProviderRegistryInterface
{
    /** @var array<string, OAuthProviderInterface> */
    private array $providers = [];

    /**
     * Register an OAuth provider under a given name.
     */
    public function register(string $name, OAuthProviderInterface $provider): void
    {
        $this->providers[$name] = $provider;
    }

    /**
     * Get a registered OAuth provider by name.
     *
     * @throws \InvalidArgumentException If the provider is not registered
     */
    public function get(string $name): OAuthProviderInterface
    {
        if (!$this->has($name)) {
            throw new \InvalidArgumentException(
                sprintf(
                    'OAuth provider "%s" is not registered. Available providers: [%s]',
                    $name,
                    implode(', ', $this->getAvailableProviders()),
                )
            );
        }

        return $this->providers[$name];
    }

    /**
     * Check if a provider is registered under the given name.
     */
    public function has(string $name): bool
    {
        return isset($this->providers[$name]);
    }

    /**
     * Get the list of all registered provider names.
     *
     * @return string[]
     */
    public function getAvailableProviders(): array
    {
        return array_keys($this->providers);
    }
}
