# PASETO access tokens

## Objective

Issue and verify encrypted access tokens with bounded parsing and a stable issuer.

## Activation and configuration

Set `better_auth.secret` to at least 32 bytes. Tune `access_token.parser` limits only from measured requirements.

## Contract and flow

`createAccessToken()` adds the subject, configured identifier, `type: access`, issuer `betterauth`, issued-at, and expiry. `parseAccessToken()` verifies all and returns claims.

## Example

```php
$token = $tokens->createAccessToken($user->getUserIdentifier(), [], 3600);
$claims = $tokens->parseAccessToken($token);
```

## Security and failures

`decode()` and `isExpired()` never authorize. Invalid, wrong-type, wrong-issuer, and expired values are unauthorized; never disclose parser detail.

## Validation

Test tampering, expiry, wrong issuer/type, missing identifier, oversized token, and deep claims.
