# Magic links

## Objective

Provide passwordless, email-mediated account confirmation with a single-use value.

## Activation and configuration

Enable `features.magic_link`, map `feature_ports.magic_link` to an implementation, and configure the application mailer. The bundle does not send mail or register a route.

## Contract and flow

Issue with purpose `magic_link`; the store receives `hash('sha256', $raw)` and expiry. Mail an HTTPS URL containing the opaque value. On the callback, `consume()` must atomically match purpose/hash/expiry/unused state and return the user identifier once.

## Example

```php
$value = $magicLinks->issue($user->getUserIdentifier());
$mailer->send(new TemplatedEmail()->to($user->getEmail())->context(['value' => $value]));
```

## Security and failures

Do not include email or account state in the URL, logs, or database. Use a short TTL, generic issuance responses, rate limits, HTTPS, and a safe post-consumption redirect. Expired or consumed values are rejected identically.

## Validation

Test issuance for known/unknown accounts, callback replay, expiry, wrong purpose, and mail delivery. Verify the persistence fixture contains only the hash.
