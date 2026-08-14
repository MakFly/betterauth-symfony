# BetterAuth Symfony Bundle 1.0

`betterauth/symfony-bundle` is a Symfony-native PASETO v4.local access-token
bundle. It never creates application entities, mappings, migrations, routes,
controllers, user providers, or firewalls. Your application owns all of those
boundaries.

## Install

```bash
composer require betterauth/symfony-bundle
```

Configure your secret and an application refresh-token store. The store receives
hashes only and implements `RefreshTokenStoreInterface`.

```yaml
# config/packages/better_auth.yaml
better_auth:
    secret: '%env(BETTER_AUTH_SECRET)%'
    user_id_claim: sub
    access_token: { ttl: 3600 }
    refresh_token:
        enabled: true
        ttl: 2592000
        store: App\Security\RefreshTokenStore
    token_extractors:
        authorization_header: { enabled: true, max_length: 8192 }
        cookie: { enabled: false, name: access_token }
```

Use the application's existing Symfony provider in the firewall. The factory
injects that provider into the authenticator; it does not inspect or assume an
entity class.

```yaml
# config/packages/security.yaml
security:
    providers:
        app_users:
            id: App\Security\UserProvider
    firewalls:
        api:
            stateless: true
            provider: app_users
            better_auth: ~
```

Issue tokens from your own login endpoint with `RefreshTokenManager`, inspect
its typed rotation outcome (`rotated`, `replayed`, or `invalid`), and use
`TokenService::createAccessToken()` / `parseAccessToken()` where access tokens
are required. The configured `user_id_claim` is both emitted and read by the
authenticator. See [the configuration reference](docs/CONFIGURATION.md).

## Optional capabilities

OAuth/OIDC, TOTP, magic links, email reset, guest sessions, device tracking,
monitoring, and multi-tenant membership are opt-in contracts only. They are
disabled by default and this bundle supplies no HTTP route for them. Register
your application implementation and route explicitly. OAuth and OIDC are safe
relying-party flows: the application supplies an atomic state transaction store;
the bundle uses state plus PKCE (and nonce for OIDC), while the OIDC port
validates the external ID token. A BetterAuth PASETO access token is never an
OIDC ID token. OAuth provider/redirect pairs are approved before any state is
stored and use S256 PKCE. TOTP persistence receives only ciphertext derived from the
bundle secret.

## Security properties

- Access tokens are authenticated PASETO v4.local tokens.
- Refresh-token persistence receives SHA-256 hashes, never raw tokens.
- Rotation is delegated to one atomic application-store operation.
- Reuse of a spent token revokes the affected user's refresh-token family.

See [UPGRADE-1.0.md](UPGRADE-1.0.md) when migrating from pre-1.0 releases.
