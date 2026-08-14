# BetterAuth Symfony

[![PHP](https://img.shields.io/badge/PHP-8.4%2B-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![Symfony](https://img.shields.io/badge/Symfony-6.4%20%7C%207.x%20%7C%208.x-000000?logo=symfony)](https://symfony.com/)
[![License](https://img.shields.io/badge/license-MIT-5c4ee5.svg)](LICENSE)

BetterAuth Symfony is Symfony authentication infrastructure for encrypted PASETO
v4.local access tokens, atomic refresh-token rotation, encrypted TOTP, and
application-owned security ports. It gives an application safe primitives; it
does not generate a User entity, routes, controllers, firewalls, Doctrine
mappings, migrations, or persistence adapters.

## Install and issue a token

```bash
composer require betterauth/symfony-bundle
```

```yaml
# config/packages/better_auth.yaml
better_auth:
    secret: '%env(BETTER_AUTH_SECRET)%'
    user_id_claim: sub
    access_token: { ttl: 900 }
    refresh_token:
        enabled: true
        ttl: 2592000
        store: App\Security\RefreshTokenStore
```

The refresh store is an application service implementing
`RefreshTokenStoreInterface`; it receives SHA-256 hashes, never a raw refresh
token. Issue the pair from an application-owned login route:

```php
$pair = $refreshTokens->issue($user->getUserIdentifier());
return new JsonResponse([
    'access_token' => $pair->accessToken,
    'refresh_token' => $pair->refreshToken,
    'expires_in' => $pair->expiresIn,
]);
```

## Protect a firewall

Your application supplies the user provider and decides which routes require a
token. The bundle factory injects that configured provider into the
authenticator.

```yaml
# config/packages/security.yaml
security:
    providers:
        app_users: { id: App\Security\UserProvider }
    firewalls:
        api:
            stateless: true
            provider: app_users
            better_auth: ~
```

```text
┌──────────────┐   Bearer PASETO   ┌─────────────────────┐
│ Application  │ ────────────────▶ │ better_auth factory │
│ route/API     │                  └──────────┬──────────┘
└──────┬───────┘                             │ claims: sub
       │ token pair                           ▼
       ▼                           ┌─────────────────────┐
┌──────────────┐  hash/ciphertext  │ Application provider │
│ Bundle APIs  │ ────────────────▶ │ and storage ports   │
└──────────────┘                   └─────────────────────┘
```

The application remains the owner of persistence, authorization rules, HTTP
schemas and error policy. The bundle translates malformed, expired, or
non-access PASETOs into Symfony authentication failures.

## Capabilities

| Capability | Bundle responsibility | Application responsibility |
| --- | --- | --- |
| PASETO access tokens | Create and parse bounded v4.local claims | User provider, firewall and routes |
| Refresh tokens | Issue, typed atomic rotate, targeted/family revoke | Transactional hash-only store |
| TOTP | Domain-separated seed encryption and verification | Ciphertext-only store and enrollment policy |
| Magic link, reset, guest | One-time hash issuance and atomic consume | Delivery, account policy and storage |
| Device and monitoring | Fingerprint/event services | Retention, alerting and storage |
| Multi-tenant | Membership query service | Membership source and authorization |

Optional features are disabled by default and register no HTTP routes. Enable a
feature only after wiring its application-owned port; see the configuration
reference for each port.

## Compatibility and security

- PHP 8.4+; Symfony 6.4, 7.x and 8.x are supported by the package matrix.
- OpenSSL is required for authenticated TOTP seed encryption.
- Use a high-entropy `BETTER_AUTH_SECRET`, rotate it through your own secret
  lifecycle, and keep access tokens short lived.
- Perform refresh rotation and one-time consumption with a conditional,
  unspent/unexpired database update. Treat a replay as a family-revocation
  event.
- Make bearer API firewalls stateless. Re-authenticate before changing a
  second factor or password; revoke refresh families after password reset.

## Documentation and migration

- [English documentation](docs/en/index.md)
- [Documentation française](docs/fr/index.md)
- [Configuration reference](docs/CONFIGURATION.md)
- [Standalone demo](examples/demo-app/README.md)
- [Upgrade to 1.1](UPGRADE-1.1.md)

The demo is outside CI and shows Doctrine/SQLite adapters, stateless bearer
authentication, Mailpit delivery, Tailwind built with Bun, and the full route
matrix. It is a teaching application: adapt its limits, mail sender, secrets,
retention and storage transactions before production use.
