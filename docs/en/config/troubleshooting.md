# Troubleshooting

## Objective

Diagnose integration failures without exposing credentials or token payloads.

## Activation and configuration

Confirm the active environment has `BETTER_AUTH_SECRET`, the intended firewall/provider, and identical configuration on every instance.

## Contract and flow

Requests extract and verify a token, then load a user. Rotation atomically consumes a hash and inserts its replacement; TOTP decrypts its seed and compares a six-digit code.

## Example

```bash
php bin/console debug:config better_auth
php bin/console cache:clear
```

## Security and failures

Always unauthorized: check exact `Bearer` syntax, issuer/secret, identifier claim, provider, and firewall. Invalid refresh: check hash/expiry/revocation and transaction. Replay: revoke all sessions. TOTP: check ciphertext and synchronized clocks.

## Validation

Reproduce with a disposable fixture, inspect statuses and request IDs, then remove it. Never enable verbose token payload logging in production.
