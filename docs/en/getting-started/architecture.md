# Architecture

## Objective

Keep the boundary clear: BetterAuth supplies authentication primitives; the application owns users and business policy.

## Activation and configuration

Configure `better_auth`, wire enabled feature ports, and attach the authenticator to the intended firewall/provider.

## Contract and flow

Request → extractor → PASETO verification → user provider → Symfony security token → controller. Refresh, one-time, TOTP, device, monitoring, and tenant services call application-owned ports.

## Example

```text
Authorization: Bearer <v4.local access token>
        -> parseAccessToken() -> loadUserByIdentifier() -> protected controller
```

## Security and failures

Only `verify()`/`parseAccessToken()` authorize; `decode()` is inspection-only. Current authorization belongs in application state.

## Validation

Trace one request through extractor, token service, provider, and firewall without logging the token. Test each configured adapter independently.
