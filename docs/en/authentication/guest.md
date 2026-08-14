# Guest access

## Objective

Offer a narrowly scoped one-time guest journey without treating a guest token as a registered account session.

## Activation and configuration

Enable `features.guest` and wire a dedicated `OneTimeTokenStoreInterface` port. Give guest tokens their own purpose (`guest`) and short TTL; never reuse the reset or magic-link store namespace accidentally.

## Contract and flow

`OneTimeTokenService::issue($guestId)` stores only a SHA-256 hash. `consume($value)` atomically returns the guest identifier once, or `null`. The controller must grant only explicitly allow-listed guest actions and require account verification before upgrade.

## Example

```php
$token = $guestTokens->issue('guest:'.$cartId);
// Send $token through the application channel; consume it once on POST.
$guestId = $guestTokens->consume($request->request->getString('token'));
```

## Security and failures

Keep guest and user identifiers separate, expire aggressively, rate-limit issuance/consumption, and do not put cart or account data in URLs. `null` must produce the same generic failure for unknown, expired, or consumed values.

## Validation

Assert a token is single-use, purpose-bound, expired after TTL, and unable to read another guest’s cart or call authenticated-user endpoints.
