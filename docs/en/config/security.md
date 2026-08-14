# Security configuration

## Objective

Make token, extraction, and optional-feature choices explicit at container build time.

## Activation and configuration

```yaml
better_auth:
    secret: '%env(BETTER_AUTH_SECRET)%'
    user_id_claim: sub
    access_token: { ttl: 3600 }
    refresh_token: { enabled: true, ttl: 2592000, store: App\Security\RefreshTokenStore }
    token_extractors: { authorization_header: { enabled: true }, cookie: { enabled: false } }
```

Set positive parser limits and enable features only with their application port. See the [configuration reference](../../CONFIGURATION.md).

## Contract and flow

Symfony loads this tree and the extension wires core services; the application supplies provider, controller, store, and transport behavior.

## Example

```dotenv
BETTER_AUTH_SECRET=secret-manager-value-at-least-32-bytes
```

## Security and failures

Keep secrets outside source control; use HTTPS, secure cookie attributes, CSRF protection, rate limits, and log redaction. Startup must fail for invalid options.

## Validation

Run `php bin/console lint:yaml config`, clear cache, inspect the compiled container, and test each enabled flow.
