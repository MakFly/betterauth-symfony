# Replay and revocation

## Objective

Give the application precise session controls and a reliable compromise signal.

## Activation and configuration

Wire `RefreshTokenStoreInterface` and enable refresh tokens. Its adapter must support `revoke($hash)`, `revokeForUser($identifier)`, and atomic `rotate()`.

## Contract and flow

An unspent/unexpired hash rotates; a spent matching hash returns `replayed`; no match, expiry, or revoked state returns `invalid`. `RefreshTokenManager::revoke()` revokes one value and `revokeAll()` revokes the user family.

## Example

```php
$refresh->revoke($request->request->getString('refresh_token'));
$refresh->revokeAll($user->getUserIdentifier());
```

## Security and failures

Persist hash, user identifier, expiry, replacement hash, and revoked state only. Treat replay as compromise, alert it, and remove stale rows safely. Do not let a client choose another user identifier.

## Validation

Exercise targeted logout, account-wide logout, replay, expiry, and concurrent rotation. Confirm old values cannot restore a revoked family.
