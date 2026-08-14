<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\DependencyInjection;

use BetterAuth\Symfony\Core\TokenService;
use BetterAuth\Symfony\Feature\DeviceService;
use BetterAuth\Symfony\Feature\OAuthService;
use BetterAuth\Symfony\Feature\OidcService;
use BetterAuth\Symfony\Feature\OneTimeTokenService;
use BetterAuth\Symfony\Feature\SecurityMonitoringService;
use BetterAuth\Symfony\Feature\TenantMembershipService;
use BetterAuth\Symfony\Feature\TotpService;
use BetterAuth\Symfony\Security\BetterAuthAuthenticator;
use BetterAuth\Symfony\Token\RefreshTokenManager;
use BetterAuth\Symfony\Token\RefreshTokenStoreInterface;
use BetterAuth\Symfony\TokenExtractor\AuthorizationHeaderTokenExtractor;
use BetterAuth\Symfony\TokenExtractor\ChainTokenExtractor;
use BetterAuth\Symfony\TokenExtractor\CookieTokenExtractor;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Reference;

final class BetterAuthExtension extends Extension
{
    public function load(array $configs, ContainerBuilder $container): void
    {
        $config = $this->processConfiguration(new Configuration(), $configs);
        $features = $this->arrayValue($config['features'] ?? null, 'features');
        $featurePorts = $this->arrayValue($config['feature_ports'] ?? null, 'feature_ports');
        $accessToken = $this->arrayValue($config['access_token'] ?? null, 'access_token');
        $parser = $this->arrayValue($accessToken['parser'] ?? null, 'access_token.parser');
        $refreshToken = $this->arrayValue($config['refresh_token'] ?? null, 'refresh_token');
        $tokenExtractors = $this->arrayValue($config['token_extractors'] ?? null, 'token_extractors');
        $headerExtractor = $this->arrayValue($tokenExtractors['authorization_header'] ?? null, 'token_extractors.authorization_header');
        $cookieExtractor = $this->arrayValue($tokenExtractors['cookie'] ?? null, 'token_extractors.cookie');
        $container->setParameter('better_auth.features', $features);

        $container->setDefinition(TokenService::class, new Definition(TokenService::class, [
            $config['secret'],
            'betterauth',
            $this->stringValue($config['user_id_claim'] ?? null, 'user_id_claim'),
            $this->positiveInt($parser['max_token_length'] ?? null, 'access_token.parser.max_token_length'),
            $this->positiveInt($parser['max_json_length'] ?? null, 'access_token.parser.max_json_length'),
            $this->positiveInt($parser['max_claim_count'] ?? null, 'access_token.parser.max_claim_count'),
            $this->positiveInt($parser['max_claim_depth'] ?? null, 'access_token.parser.max_claim_depth'),
        ]))->setPublic(true);

        $extractors = [];
        if ($this->boolValue($headerExtractor['enabled'] ?? null, 'token_extractors.authorization_header.enabled')) {
            $headerId = 'better_auth.token_extractor.authorization_header';
            $container->setDefinition($headerId, new Definition(AuthorizationHeaderTokenExtractor::class, [
                $this->positiveInt($headerExtractor['max_length'] ?? null, 'token_extractors.authorization_header.max_length'),
            ]));
            $extractors[] = new Reference($headerId);
        }
        if ($this->boolValue($cookieExtractor['enabled'] ?? null, 'token_extractors.cookie.enabled')) {
            $cookieId = 'better_auth.token_extractor.cookie';
            $container->setDefinition($cookieId, new Definition(CookieTokenExtractor::class, [
                $this->stringValue($cookieExtractor['name'] ?? null, 'token_extractors.cookie.name'),
                $this->positiveInt($cookieExtractor['max_length'] ?? null, 'token_extractors.cookie.max_length'),
            ]));
            $extractors[] = new Reference($cookieId);
        }
        if ($extractors === []) {
            throw new \LogicException('At least one BetterAuth token extractor must be enabled.');
        }

        $container->setDefinition('better_auth.token_extractor', new Definition(ChainTokenExtractor::class, [$extractors]));
        $container->setDefinition(BetterAuthAuthenticator::class, new Definition(BetterAuthAuthenticator::class, [
            new Reference(TokenService::class),
            new Reference('better_auth.token_extractor'),
            null,
            $this->stringValue($config['user_id_claim'] ?? null, 'user_id_claim'),
        ]));

        if ($this->boolValue($refreshToken['enabled'] ?? null, 'refresh_token.enabled')) {
            if (($refreshToken['store'] ?? null) === null) {
                throw new \LogicException('better_auth.refresh_token.store is required when refresh tokens are enabled.');
            }
            $store = $this->stringValue($refreshToken['store'], 'refresh_token.store');
            $container->setDefinition(RefreshTokenManager::class, new Definition(RefreshTokenManager::class, [
                new Reference(TokenService::class),
                new Reference($store),
                $this->positiveInt($accessToken['ttl'] ?? null, 'access_token.ttl'),
                $this->positiveInt($refreshToken['ttl'] ?? null, 'refresh_token.ttl'),
                true,
            ]))->setPublic(true);
            $container->setAlias(RefreshTokenStoreInterface::class, $store);
        }

        $this->registerOptionalFeatures($container, $features, $featurePorts, $config['oidc_issuer'] ?? null, $config['secret'] ?? null);
    }

    /** @return array<string, mixed> */
    private function arrayValue(mixed $value, string $name): array
    {
        if (!is_array($value)) {
            throw new \LogicException(sprintf('better_auth.%s must be an array.', $name));
        }

        $normalised = [];
        foreach ($value as $key => $item) {
            if (is_string($key)) {
                $normalised[$key] = $item;
            }
        }

        return $normalised;
    }

    private function boolValue(mixed $value, string $name): bool
    {
        if (!is_bool($value)) {
            throw new \LogicException(sprintf('better_auth.%s must be a boolean.', $name));
        }

        return $value;
    }

    private function stringValue(mixed $value, string $name): string
    {
        if (!is_string($value) || $value === '') {
            throw new \LogicException(sprintf('better_auth.%s must be a non-empty string.', $name));
        }

        return $value;
    }

    private function positiveInt(mixed $value, string $name): int
    {
        if (!is_int($value) || $value < 1) {
            throw new \LogicException(sprintf('better_auth.%s must be a positive integer.', $name));
        }

        return $value;
    }

    /**
     * @param array<string, mixed> $features
     * @param array<string, mixed> $ports
     */
    private function registerOptionalFeatures(ContainerBuilder $container, array $features, array $ports, mixed $oidcIssuer, mixed $secret): void
    {
        if ($this->featureEnabled($features, 'oauth')) {
            $this->registerFeature($container, 'oauth', OAuthService::class, [new Reference($this->port($ports, 'oauth')), new Reference($this->port($ports, 'authorization_transactions'))]);
        }
        if ($this->featureEnabled($features, 'oidc')) {
            $issuer = $this->stringValue($oidcIssuer, 'oidc_issuer');
            $this->registerFeature($container, 'oidc', OidcService::class, [new Reference($this->port($ports, 'oidc')), new Reference($this->port($ports, 'authorization_transactions')), $issuer]);
        }
        if ($this->featureEnabled($features, 'totp')) {
            $this->registerFeature($container, 'totp', TotpService::class, [new Reference($this->port($ports, 'totp')), $this->stringValue($secret, 'secret')]);
        }
        foreach (['magic_link' => [900, 'magic-link'], 'email_reset' => [3600, 'email-reset'], 'guest' => [86400, 'guest']] as $name => [$ttl, $purpose]) {
            if ($this->featureEnabled($features, $name)) {
                $this->registerFeature($container, $name, OneTimeTokenService::class, [new Reference($this->port($ports, $name)), $purpose, $ttl]);
            }
        }
        if ($this->featureEnabled($features, 'device')) {
            $this->registerFeature($container, 'device', DeviceService::class, [new Reference($this->port($ports, 'device'))]);
        }
        if ($this->featureEnabled($features, 'monitoring')) {
            $this->registerFeature($container, 'monitoring', SecurityMonitoringService::class, [new Reference($this->port($ports, 'monitoring'))]);
        }
        if ($this->featureEnabled($features, 'multi_tenant')) {
            $this->registerFeature($container, 'multi_tenant', TenantMembershipService::class, [new Reference($this->port($ports, 'multi_tenant'))]);
        }
    }

    /** @param array<string, mixed> $features */
    private function featureEnabled(array $features, string $name): bool
    {
        return $this->boolValue($features[$name] ?? null, sprintf('features.%s', $name));
    }

    /** @param array<string, mixed> $ports */
    private function port(array $ports, string $name): string
    {
        return $this->stringValue($ports[$name] ?? null, sprintf('feature_ports.%s', $name));
    }

    /** @param list<mixed> $arguments */
    private function registerFeature(ContainerBuilder $container, string $name, string $class, array $arguments): void
    {
        $container->setDefinition(sprintf('better_auth.feature.%s', $name), new Definition($class, $arguments))->setPublic(true);
    }
}
