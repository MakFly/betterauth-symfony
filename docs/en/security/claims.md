# Claims

## Objective

Define a small, interoperable claim contract for authentication.

## Activation and configuration

The default identifier is `sub`; set `user_id_claim` only when issuer, provider, and consumer agree. Parser limits live under `access_token.parser`.

## Contract and flow

Access tokens carry `sub`, `type`, `iat`, `exp`, and `iss`; issuance accepts custom scalar/structured claims. The authenticator uses the configured identifier for provider lookup.

## Example

```php
$tokens->createAccessToken($id, ['tenant' => 'acme', 'scope' => ['read']], 900);
```

## Security and failures

Keep claims small, stable, and non-sensitive. Current roles and membership belong in application state. Oversized/deep claims are rejected before authorization.

## Validation

Test custom round-trip, identifier changes, parser limits, and absence of passwords/secrets.
