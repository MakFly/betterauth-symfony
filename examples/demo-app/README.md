# BetterAuth local demo

This standalone Symfony 8.1/PHP 8.5 example is intentionally outside package
CI. It uses SQLite, Doctrine ORM, Twig, Tailwind v4 (Bun only), and Mailpit at
`127.0.0.1:1025` or `infra-mailpit:1025`; it creates no container.

```bash
composer install
php bin/console doctrine:migrations:migrate --no-interaction
bun install
bun run build
php -S 127.0.0.1:8080 -t public
```

The UI demonstrates registration; the JSON API implements login, a stateless
PASETO-protected `/api/me`, atomic refresh rotation/replay family revocation,
targeted/account-wide logout, TOTP, magic link, password reset, guest, device,
monitoring, and tenant checks. Magic-link and reset issue requests send the
one-time value through the configured Mailpit SMTP endpoint while returning the
same generic response for known and unknown accounts. Responses use
`application/problem+json` for errors, including `429` rate limits by IP and
hashed identity. SQLite receives refresh/one-time hashes and active/pending
TOTP ciphertext only; it never receives their raw values.

## Route matrix

| Route | Contract |
| --- | --- |
| `POST /api/register`, `POST /api/login` | Generic accepted registration; PASETO pair login. Both are rate limited. |
| `GET /api/me` | Requires `Authorization: Bearer <access>` on every request; the firewall is stateless. |
| `POST /api/refresh`, `/api/logout`, `/api/logout/all` | Atomic rotate/replay family revoke; targeted revoke; authenticated family revoke. |
| `POST /api/totp/enroll`, `/api/totp/confirm`, `/api/totp/verify` | Current-password re-auth creates a ten-minute pending seed; a valid code promotes it atomically. |
| `POST /api/magic-link*`, `/api/password-reset*`, `/api/guest*` | Generic Mailpit issue, atomic single consume, expiry and rate limits. Reset success revokes every refresh token. |
| `POST /api/devices`, `/api/monitoring`; `GET /api/tenant/{tenant}` | Authenticated device/event persistence and membership allow/deny. |

Run the demo gates without a server:

```bash
composer validate --strict
php bin/console doctrine:migrations:migrate --no-interaction
vendor/bin/phpunit --no-coverage
vendor/bin/phpstan analyse --memory-limit=256M
bun install && bun run build
```
