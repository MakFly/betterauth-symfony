# Demo application

## Objective

Exercise the complete v1.1 integration locally, including browser UI, JSON API, persistence, mail, and styling.

## Activation and configuration

From `examples/demo-app/`, run `composer install`, then migrate SQLite and build assets. Start Mailpit separately at `127.0.0.1:1025` (or `infra-mailpit:1025`).

## Contract and flow

The demo exposes registration/login, PASETO-protected `/api/me`, refresh rotation and replay revocation, logout, TOTP, magic link, password reset, guest, device, monitoring, and tenant checks.

## Route matrix

| Area | Route(s) | Auth |
| --- | --- | --- |
| UI | `/` | public |
| Session | `/api/register`, `/api/login`, `/api/refresh`, `/api/logout` | mixed |
| Protected | `/api/me`, `/api/totp/*`, `/api/devices`, `/api/tenant/{tenant}` | access token |
| One-time | `/api/magic-link/*`, `/api/password-reset/*`, `/api/guest/*` | opaque value |

## Example

```bash
cd examples/demo-app
composer install
php bin/console doctrine:migrations:migrate --no-interaction
bun install && bun run build
php -S 127.0.0.1:8080 -t public
```

## Security and failures

SQLite stores refresh/one-time hashes and TOTP ciphertext only. Mail responses are generic for known/unknown accounts; API errors use `application/problem+json`.

## Validation

Open `http://127.0.0.1:8080`, inspect Mailpit, exercise the route matrix, replay one refresh value, and verify the second request is rejected.
