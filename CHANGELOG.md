# Changelog

All notable changes to `betterauth/symfony-bundle` are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.0.23] - 2026-06-21

Re-release of 0.0.22. The 0.0.22 release automation failed on a locked tag, so its git tag
(while retained and installable) has no published GitHub release; this patch re-issues the
same dependency-constraint fix under a clean tag. No code changes since 0.0.22.

## [0.0.22] - 2026-06-21

### Fixed
- Dependency constraint for `betterauth/multimodal-php` now allows the tagged `0.0.x` line
  (`~0.0.3 || dev-main`). The previous `^0.1` matched no published `0.0.x` release, so a
  tagged install could only resolve the Core package from the `dev-main` branch.

## [0.0.21] - 2026-06-21

### Added
- **Opaque session id.** Sessions now carry an opaque `id` (decoupled from the secret
  token) usable as a safe revocation/listing handle. `DoctrineSessionRepository` implements
  `findById()` / `deleteById()` and persists the id verbatim (never hashed).
  `SessionController::list()` exposes `id` and resolves the `current` flag from the bearer
  token via `AuthManager::validateSession()` (the previous token comparison was always
  false once tokens became hashed at rest).
- `login`/`login2fa` now verify credentials via `AuthManager::verifyCredentials()` and
  create the session/tokens via `completeSignIn()`, removing the redundant second password
  verification while keeping the 2FA gate before any session/token is created.

### Database
- Migration `Version20260621120000`: adds a nullable, unique `id` column to `sessions`
  and backfills an opaque id for every pre-existing row.

### Fixed
- **Security:** stopped logging the SMTP DSN (which contains the SMTP password) and the
  email verification link/token in `SymfonyMailerEmailSender`.
- `DoctrineUserRepository::create()/update()` now read the canonical `password_hash` key.
  Sign-up on the Doctrine path persisted the password as `NULL`, so every subsequent login
  failed with `InvalidCredentialsException` — now fixed.
- Password-less guest conversion no longer fails on a random-password sign-in; it issues
  tokens for the converted user (or returns `400` in session-only mode).
- `SessionController::list()` guards `validateSession()` by session mode, so listing no
  longer errors in pure API mode.

## [0.0.20] - 2026-06-17

### Security
- **Brute-force protection on login.** `CredentialsController::login()` and `login2fa()` are
  now rate-limited (5 attempts / 15 min per IP+email) *before* the manual password/TOTP
  checks. Previously the rate limiter was only reached after a successful password, leaving
  password and TOTP brute-force unbounded.
- **Open-redirect / token exfiltration fixed.** Magic-link and email-verification endpoints
  validate the user-supplied `callbackUrl` against the trusted frontend origin before
  embedding it in the emailed link (returns `400` on a foreign host).
- **Session tokens hashed at rest.** `DoctrineSessionRepository` stores `sha256(token)` and
  looks up by hash. A DB read no longer yields usable session tokens.
- **TOTP secret encryption at rest.** `DoctrineTotpRepository` encrypts the TOTP seed with
  AES-256-GCM, keyed from `%better_auth.secret%`.
- **Refresh token `replaced_by` hashed** for consistency (no raw replacement token stored).
- **Cookie security flags declared.** `cookie_secure` / `cookie_http_only` / `cookie_same_site`
  are now part of the configuration tree (the recipe shipped them but they were undeclared,
  which could break boot or silently drop them). Secure defaults: `true` / `true` / `lax`.

### Changed
- Session-mode auth responses no longer duplicate the session token as `refresh_token`
  (it is now `null` — session mode has no separate rotatable credential).
- Emails are masked in failure logs (`ke***@domain`) to avoid leaking PII into centralized logs.
- `QueryParameterTokenExtractor` documentation strengthened; it remains **off by default**
  (not part of the default `ChainTokenExtractor`).

### Migration notes
- **Existing data migrates automatically and transparently after deploy — no manual step,
  no forced logout.** When the patched code goes live (e.g. via `composer update`):
  - legacy plaintext **session** rows are rehashed in place on first access;
  - legacy plaintext **TOTP** secrets are re-encrypted in place on first access (the
    `enc:v1:` marker distinguishes encrypted from legacy values).
- A literal `composer install` hook is intentionally **not** used: dependency composer
  scripts do not run in the consumer project, and auto-running DB migrations at build time
  would break CI. The self-migrating repositories achieve the same result safely.
- Keep `BETTER_AUTH_SECRET` stable — rotating it makes already-encrypted TOTP secrets
  unreadable.

## [0.0.19] - and earlier

- See git history.

[0.0.20]: https://github.com/MakFly/betterauth-symfony/compare/v0.0.19...v0.0.20
[0.0.19]: https://github.com/MakFly/betterauth-symfony/releases/tag/v0.0.19
