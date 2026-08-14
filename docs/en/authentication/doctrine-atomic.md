# Atomic Doctrine storage

## Objective

Make refresh rotation one-use under concurrent requests. Doctrine is an application adapter; the bundle does not create entities or migrations.

## Activation and configuration

Implement `RefreshTokenStoreInterface`, wire its service, and reference it from `refresh_token.store`:

```yaml
better_auth:
    refresh_token: { enabled: true, store: App\Security\DoctrineRefreshTokenStore }
```

## Contract and flow

`rotate(currentHash, replacementHash, expiresAt, now)` must atomically match an unrevoked, unexpired row, mark it spent, and insert one replacement. Return `Rotated` with the user record; return `Replayed` for a spent match and `Invalid` otherwise.

## Example

Use one Doctrine transaction: `UPDATE ... WHERE hash = :current AND revoked = false AND expires_at > :now`; require one affected row, then insert the replacement before commit. Add a unique index on the hash.

## Security and failures

Never persist raw refresh values. A transaction that commits consumption without its replacement can log users out; a non-atomic check-then-write permits double use. On replay, revoke the whole user family.

## Validation

Run two concurrent rotations against one fixture and assert exactly one `rotated`, one `replayed`/`invalid`, one replacement, and no duplicate hash. Test expired and revoked rows.
