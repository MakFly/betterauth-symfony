# Password reset

## Objective

Recover an account through a short-lived email proof, then invalidate sessions that may have been compromised.

## Activation and configuration

Enable `features.email_reset`, wire `feature_ports.email_reset`, and provide an application mailer and password hasher. The bundle provides no reset controller or user entity.

## Contract and flow

Issue a one-time value with purpose `email_reset`; consume it atomically; hash the new password; and call `RefreshTokenManager::revokeAll($userId)` in one application transaction where possible.

## Example

```php
$value = $resetTokens->issue($userId);
// Mail an HTTPS URL carrying only $value; POST the new password after consume().
$user->setPassword($hasher->hashPassword($user, $password));
$refresh->revokeAll($userId);
```

## Security and failures

Return the same response for registered and unknown addresses. Rate-limit requests and attempts, never log/store raw values, enforce password policy, and invalidate prior reset values after success.

## Validation

Test generic responses, one-use/expiry, wrong-purpose rejection, password hash verification, and refresh-family revocation after reset.
