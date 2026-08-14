# Installation

## Objective

Install the bundle while keeping users, routes, persistence, and migrations application-owned.

## Activation and configuration

```bash
composer require betterauth/symfony-bundle
php bin/console cache:clear
```

Register `BetterAuth\Symfony\BetterAuthBundle` in `config/bundles.php` when Flex has not done so. Set `BETTER_AUTH_SECRET` to at least 32 bytes and configure the firewall/provider. See [security configuration](../config/security.md).

## Contract and flow

The bundle registers services and ports; it creates no routes, controllers, entities, mappings, or migrations. Controllers issue tokens and application stores persist hashes/ciphertext.

## Example

```yaml
better_auth:
    secret: '%env(BETTER_AUTH_SECRET)%'
```

## Security and failures

Use a secret manager in deployed environments, identical key material across instances, HTTPS, and redacted logs. Missing or short secrets must fail startup.

## Validation

Run `php bin/console lint:yaml config`, clear cache, call a protected route, and assert absent/malformed tokens are unauthorized.
