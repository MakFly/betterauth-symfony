# BetterAuth Symfony Bundle — PASETO Authentication for Symfony

[![Latest Stable Version](https://poser.pugx.org/betterauth/symfony-bundle/v/stable)](https://packagist.org/packages/betterauth/symfony-bundle)
[![Tests](https://github.com/MakFly/betterauth-symfony/actions/workflows/tests.yml/badge.svg)](https://github.com/MakFly/betterauth-symfony/actions/workflows/tests.yml)
[![PHP Version](https://img.shields.io/packagist/dependency-v/betterauth/symfony-bundle/php)](https://packagist.org/packages/betterauth/symfony-bundle)
[![Symfony](https://img.shields.io/badge/Symfony-6.4%20%7C%207.x%20%7C%208.x-000000?logo=symfony)](https://symfony.com/)
[![License](https://poser.pugx.org/betterauth/symfony-bundle/license)](LICENSE)

**BetterAuth Symfony Bundle is a Symfony-native authentication package that adds encrypted PASETO v4.local access tokens, secure refresh-token rotation, and opt-in OAuth, OIDC, and TOTP services without taking ownership of your application.**

Your Symfony application keeps its `User`, user provider, firewall, routes,
controllers, and persistence model. The bundle supplies the authentication
services and explicit storage contracts.

> **Quick answer:** choose BetterAuth when you want token authentication that
> integrates with Symfony Security like a focused infrastructure bundle—not an
> authentication application that generates your domain model or HTTP API.

## Why use BetterAuth for Symfony authentication?

- **Symfony Security integration:** enable `better_auth: ~` in an existing
  firewall and keep the configured application user provider.
- **PASETO v4.local tokens:** authenticated encryption instead of a custom JWT
  implementation.
- **Secure refresh rotation:** raw refresh tokens never reach persistence;
  replay is typed and can revoke the affected token family.
- **Application-owned architecture:** no generated entities, Doctrine mappings,
  migrations, controllers, routes, firewalls, or PDO adapters.
- **Safe optional features:** OAuth/OIDC state, PKCE and nonce orchestration;
  encrypted TOTP seeds; atomic one-time-token contracts.
- **Bounded parsing:** configurable token size, JSON size, claim count, and
  claim-depth limits.
- **Tested compatibility:** the release workflow covers Symfony 6.4, 7.4,
  8.0, and the latest PHP 8.5 / Symfony 8.1 pair before publishing.

## Architecture

```text
╔══════════════════════════════════╗
║ Your Symfony application         ║
║ User · provider · routes · DB    ║
╚════════════════╤═════════════════╝
                 │ firewall config / service ports
                 ▼
╔══════════════════════════════════╗
║ BetterAuth Symfony Bundle        ║
║ authenticator · PASETO · refresh ║
║ OAuth/OIDC · TOTP · one-time     ║
╚════════════════╤═════════════════╝
                 │ hashes / ciphertext / typed outcomes
                 ▼
┌──────────────────────────────────┐
│ Application persistence adapters │
└──────────────────────────────────┘
```

Legend: double-line boxes are ownership boundaries; the single-line box is an
adapter implemented by the consuming application. BetterAuth never writes raw
refresh tokens or raw TOTP seeds to that adapter.

## Requirements

| Dependency | Supported versions |
| --- | --- |
| PHP | 8.4 or newer |
| Symfony | 6.4, 7.x, or 8.x |
| OpenSSL extension | Required |

## Installation

Install the stable v1 package from Packagist:

```bash
composer require betterauth/symfony-bundle:^1.0
```

Generate a secret with at least 32 bytes of entropy and expose it through your
deployment secret manager:

```bash
php -r "echo base64_encode(random_bytes(32)), PHP_EOL;"
```

```dotenv
BETTER_AUTH_SECRET=replace-with-the-generated-value
```

## Quick start

### 1. Configure PASETO access tokens

Start without refresh tokens if you only need stateless access-token
authentication:

```yaml
# config/packages/better_auth.yaml
better_auth:
    secret: '%env(BETTER_AUTH_SECRET)%'
    user_id_claim: sub
    access_token:
        ttl: 3600
    refresh_token:
        enabled: false
    token_extractors:
        authorization_header:
            enabled: true
            max_length: 8192
        cookie:
            enabled: false
```

### 2. Enable BetterAuth on your existing firewall

The bundle uses the provider already configured for the firewall. It does not
inspect or assume an `App\\Entity\\User` class.

```yaml
# config/packages/security.yaml
security:
    providers:
        app_users:
            id: App\Security\UserProvider

    firewalls:
        api:
            pattern: ^/api
            stateless: true
            provider: app_users
            better_auth: ~
```

Clients authenticate with a bearer token:

```http
Authorization: Bearer v4.local...
```

Expired, malformed, oversized, and non-access tokens are rejected through
Symfony's normal authentication-failure path with HTTP `401`.

### 3. Issue an access token from your own login endpoint

Your controller owns credential verification and the response format. Inject
`TokenService` after Symfony has authenticated the user:

```php
use BetterAuth\Symfony\Core\TokenService;

$accessToken = $tokenService->createAccessToken(
    subject: $user->getUserIdentifier(),
    claims: ['roles' => $user->getRoles()],
    expiresIn: 3600,
);
```

The configured `user_id_claim` is emitted into the token and later passed to
`UserProviderInterface::loadUserByIdentifier()`.

## How do refresh tokens work?

Enable refresh tokens only after implementing
`BetterAuth\Symfony\Token\RefreshTokenStoreInterface`:

```yaml
better_auth:
    secret: '%env(BETTER_AUTH_SECRET)%'
    refresh_token:
        enabled: true
        ttl: 2592000
        store: App\Security\RefreshTokenStore
```

The application store receives SHA-256 hashes only. Its `rotate()` method must
atomically consume one unspent, unexpired record and persist exactly one
replacement in the same transaction or compare-and-swap operation.

```php
use BetterAuth\Symfony\Token\RefreshRotationStatus;
use BetterAuth\Symfony\Token\RefreshTokenManager;

$pair = $refreshTokens->issue($user->getUserIdentifier());
$outcome = $refreshTokens->rotate($presentedRefreshToken);

if ($outcome->status === RefreshRotationStatus::Rotated) {
    $nextPair = $outcome->tokens;
}

if ($outcome->status === RefreshRotationStatus::Replayed) {
    // The manager has requested family revocation through the store.
}
```

Available outcomes are `rotated`, `replayed`, and `invalid`. Targeted logout is
available through `RefreshTokenManager::revoke()`; account-wide logout uses
`RefreshTokenManager::revokeAll()`.

## Which optional authentication features are included?

All optional services are disabled by default. Enabling one requires the
matching application-owned port and never creates an HTTP route.

| Feature | Bundle service | Application port responsibility |
| --- | --- | --- |
| OAuth login | `OAuthService` | Provider allowlist and authorization-code exchange |
| OpenID Connect login | `OidcService` | External ID-token signature, issuer, audience, and nonce validation |
| TOTP two-factor auth | `TotpService` | Persist authenticated ciphertext only |
| Magic link | `OneTimeTokenService` | Atomic hash consumption |
| Password reset | `OneTimeTokenService` | Atomic hash consumption |
| Guest session | `OneTimeTokenService` | Atomic hash consumption |
| Device tracking | `DeviceService` | Device record persistence |
| Security monitoring | `SecurityMonitoringService` | Security event persistence |
| Multi-tenancy | `TenantMembershipService` | Membership lookup |

OAuth and OIDC are relying-party flows. BetterAuth generates state and an S256
PKCE verifier; OIDC also generates a nonce. The application transaction store
must consume each unexpired state exactly once. A BetterAuth PASETO token is
never presented as an OIDC ID token.

See the complete [configuration reference](docs/CONFIGURATION.md) for feature
flags and port service IDs.

## Security model

BetterAuth v1 makes the trust boundaries explicit:

- access tokens use PASETO `v4.local` authenticated encryption;
- token parsing enforces configurable size and claim-complexity limits;
- refresh persistence receives hashes, never bearer credentials;
- refresh rotation and one-time links require atomic consume operations;
- replayed refresh tokens trigger family revocation;
- TOTP seeds are encrypted with a domain-separated key before persistence;
- OAuth redirect pairs are approved before state is stored;
- OAuth/OIDC use state and S256 PKCE, while OIDC also binds a nonce;
- cookie extraction is opt-in and query-string token extraction is not shipped;
- error responses do not expose token-validation details.

The consuming application remains responsible for credential verification,
authorization rules, endpoint rate limiting, response cookies, CSRF protection
when cookies are enabled, key rotation, persistence transactions, and secret
management.

## What BetterAuth does not generate

BetterAuth deliberately ships no:

- `User` entity or user provider;
- Doctrine mapping, migration, or repository implementation;
- login, registration, refresh, logout, OAuth, or recovery route;
- controller or API Platform resource;
- firewall or access-control rule;
- installer that writes into the consuming project.

This boundary keeps framework integration reusable without coupling the bundle
to an application's domain or database schema.

## Configuration and migration guides

- [Full configuration reference](docs/CONFIGURATION.md)
- [Upgrade from pre-1.0 releases](UPGRADE-1.0.md)
- [Changelog](CHANGELOG.md)
- [Latest GitHub release](https://github.com/MakFly/betterauth-symfony/releases/latest)
- [Packagist package](https://packagist.org/packages/betterauth/symfony-bundle)

## Development and verification

```bash
composer install
vendor/bin/phpunit
vendor/bin/phpstan analyse --no-progress --memory-limit=512M
composer audit --no-interaction --locked
```

The GitHub Actions matrix constrains the direct Symfony dependencies and runs
the test suite, PHPStan, and Composer audit against PHP 8.4 with Symfony 6.4,
7.4, and 8.0, plus PHP 8.5 with Symfony 8.1. The same reusable matrix blocks
every tagged release.

## Frequently asked questions

### Does BetterAuth replace Symfony Security?

No. It integrates with Symfony Security through an authenticator factory. Your
firewall, provider, user object, and authorization rules remain Symfony-owned.

### Does BetterAuth create database tables or migrations?

No. Applications implement the documented storage ports with their preferred
database and transaction model.

### Is BetterAuth an OAuth or OpenID Connect provider?

No. The optional OAuth and OIDC services orchestrate secure relying-party login
flows. The application port communicates with the external provider and, for
OIDC, validates the external ID token.

### Why PASETO instead of a custom JWT implementation?

PASETO defines versioned, purpose-specific cryptographic protocols. This bundle
uses `v4.local` for encrypted access tokens and delegates the implementation to
the maintained `paragonie/paseto` library.

### Can I use BetterAuth with an existing Doctrine User entity?

Yes. Keep the entity and provider already used by your firewall. BetterAuth
loads the user through that provider and has no Doctrine runtime dependency.

## License

BetterAuth Symfony Bundle is available under the [MIT License](LICENSE).
