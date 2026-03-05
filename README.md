# BetterAuth for Symfony

[![CI](https://github.com/MakFly/betterauth-symfony/actions/workflows/tests.yml/badge.svg?branch=main&event=push)](https://github.com/MakFly/betterauth-symfony/actions/workflows/tests.yml?query=branch%3Amain)
[![Latest Stable Version](https://img.shields.io/packagist/v/betterauth/symfony-bundle?label=stable)](https://packagist.org/packages/betterauth/symfony-bundle)
[![Total Downloads](https://img.shields.io/packagist/dt/betterauth/symfony-bundle?label=downloads)](https://packagist.org/packages/betterauth/symfony-bundle)
[![PHP Version](https://img.shields.io/packagist/php-v/betterauth/symfony-bundle?label=php)](https://packagist.org/packages/betterauth/symfony-bundle)
[![License](https://img.shields.io/packagist/l/betterauth/symfony-bundle?label=license)](LICENSE)

Modern authentication bundle for Symfony with API/session/hybrid modes, token rotation, OAuth, 2FA, and optional multi-tenant features.

## TL;DR

```bash
composer require betterauth/symfony-bundle
php bin/console better-auth:install
php bin/console better-auth:setup-features --preset=full --with-controllers --migrate
```

Then check generated routes:

```bash
php bin/console debug:router | grep auth
```

## Compatibility

- PHP: `^8.4`
- Symfony: `^6.4 | ^7.0 | ^8.0`
- API Platform: `^4.0`
- Doctrine ORM: `^3.0`
- Doctrine Migrations Bundle: `^4.0`
- Databases tested in CI:
1. PostgreSQL
2. SQLite
3. MySQL
4. MariaDB

## Database URLs

Use one of these `DATABASE_URL` values:

```bash
# PostgreSQL
DATABASE_URL=postgresql://app:secret@127.0.0.1:5432/app?serverVersion=16&charset=utf8

# SQLite
DATABASE_URL=sqlite:///%kernel.project_dir%/var/data.db

# MySQL
DATABASE_URL=mysql://app:secret@127.0.0.1:3306/app?serverVersion=8.4&charset=utf8mb4

# MariaDB
DATABASE_URL=mysql://app:secret@127.0.0.1:3306/app?serverVersion=mariadb-11.0.2&charset=utf8mb4
```

## Main Console Commands

- `better-auth:install`
- `better-auth:setup-features`
- `better-auth:add-controller`
- `better-auth:user-fields`
- `better-auth:configure`
- `better-auth:switch-mode`
- `better-auth:generate-config`
- `better-auth:generate-secret`
- `better-auth:setup:dependencies`
- `better-auth:setup:logging`
- `better-auth:config:update`
- `better-auth:publish-templates`
- `better-auth:cleanup:sessions`
- `better-auth:cleanup:tokens`

## Documentation

Start here:

- [Documentation Index](docs/00-INDEX.md)
- [Installation](docs/01-INSTALLATION.md)
- [Configuration](docs/02-CONFIGURATION.md)

Most-used guides:

- [API Mode](docs/03-API-MODE.md)
- [Session Mode](docs/04-SESSION-MODE.md)
- [Hybrid Mode](docs/05-HYBRID-MODE.md)
- [OAuth Providers](docs/06-OAUTH-PROVIDERS.md)
- [Two-Factor Auth (TOTP)](docs/07-TWO-FACTOR-AUTH.md)
- [Security Guide](docs/11-SECURITY.md)
- [Testing](docs/12-TESTING.md)
- [Migration Guide](docs/14-MIGRATION.md)
- [Entity Customization](docs/16-ENTITY-CUSTOMIZATION.md)
- [Controllers](docs/18-CONTROLLERS.md)
- [Advanced Customization](docs/19-CUSTOMIZATION.md)

## Security Note

This bundle uses Paseto V4 and supports hardening through configuration, rate limiting strategy, and security monitoring options. For production hardening checklist, see [docs/11-SECURITY.md](docs/11-SECURITY.md).

## License

MIT. See [LICENSE](LICENSE).
