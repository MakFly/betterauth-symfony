# TOTP

## Objective

Add a time-based second factor while keeping the seed encrypted at rest.

## Activation and configuration

Enable `features.totp`, map `feature_ports.totp` to `TotpStoreInterface`, and keep the BetterAuth secret at least 32 bytes. The application may use `bacon/bacon-qr-code` to render the returned URI.

## Contract and flow

`enroll($userId, $label)` returns `secret` and `provisioning_uri`; the store receives authenticated ciphertext. `verify($userId, $code, $window)` accepts six digits, SHA-256, 30-second periods, and window 0–2.

## Example

```php
$setup = $totp->enroll($user->getUserIdentifier(), $user->getEmail());
$valid = $totp->verify($user->getUserIdentifier(), $request->request->getString('code'), 1);
```

## Security and failures

Show the secret only during protected enrollment, require recent authentication, synchronize clocks, rate-limit attempts, and never log the seed/code. A decrypt failure or missing seed returns false.

## Validation

Test valid/current and boundary-window codes, malformed codes, windows outside 0–2, missing/corrupt ciphertext, and encrypted-at-rest storage.
