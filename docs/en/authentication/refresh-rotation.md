# Refresh rotation

## Objective

Keep short-lived access tokens usable while making every refresh value one-use and replay-detectable.

## Activation and configuration

Set `refresh_token.enabled: true`, choose a TTL, and wire `RefreshTokenStoreInterface`:

```yaml
better_auth:
    access_token: { ttl: 3600 }
    refresh_token: { enabled: true, ttl: 2592000, store: App\Security\RefreshStore }
```

## Contract and flow

`issue()` returns `TokenPair(accessToken, refreshToken, accessTtl)` and stores only the refresh hash. `rotate()` atomically spends the supplied hash and creates one replacement. Its outcome is `rotated`, `replayed`, or `invalid`.

## Example

```php
$pair = $refreshManager->issue($user->getUserIdentifier());
$outcome = $refreshManager->rotate($request->request->getString('refresh_token'));
```

## Security and failures

Discard old values after success. On `replayed`, revoke all sessions for the returned user and require sign-in. Never log raw values; use an atomic adapter and rate-limit the endpoint.

## Validation

Test disabled mode, issue/rotate success, parallel use, expired/revoked values, and replay family revocation. Assert only hashes reach persistence.
