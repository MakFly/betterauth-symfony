# BetterAuth Symfony - Documentation

Modern, secure authentication for Symfony 6/7 applications.

## Quick Links

- [Installation](01-INSTALLATION.md)
- [Configuration](02-CONFIGURATION.md)
- [API Reference](09-API-REFERENCE.md)

---

## Table of Contents

### Getting Started
| Document | Description |
|----------|-------------|
| [01-INSTALLATION](01-INSTALLATION.md) | Complete installation guide with commands |
| [02-CONFIGURATION](02-CONFIGURATION.md) | All configuration options explained |

### Authentication Modes
| Document | Description |
|----------|-------------|
| [03-API-MODE](03-API-MODE.md) | Stateless authentication with Paseto V4 tokens |
| [04-SESSION-MODE](04-SESSION-MODE.md) | Cookie-based session authentication |
| [05-HYBRID-MODE](05-HYBRID-MODE.md) | Combined tokens + sessions |

### Features
| Document | Description |
|----------|-------------|
| [06-OAUTH-PROVIDERS](06-OAUTH-PROVIDERS.md) | Google, GitHub, Microsoft, Facebook, Discord |
| [07-TWO-FACTOR-AUTH](07-TWO-FACTOR-AUTH.md) | TOTP with Google Authenticator, Authy |
| [08-EVENTS](08-EVENTS.md) | All events with subscriber examples |

### Reference
| Document | Description |
|----------|-------------|
| [09-API-REFERENCE](09-API-REFERENCE.md) | All endpoints with curl examples |
| [10-ERROR-HANDLING](10-ERROR-HANDLING.md) | Exceptions and error responses |
| [11-SECURITY](11-SECURITY.md) | Best practices and hardening |

### Advanced
| Document | Description |
|----------|-------------|
| [12-TESTING](12-TESTING.md) | Testing authentication flows |
| [13-TROUBLESHOOTING](13-TROUBLESHOOTING.md) | Common issues and solutions |
| [14-MIGRATION](14-MIGRATION.md) | Migration from LexikJWT and others |

### Customization
| Document | Description |
|----------|-------------|
| [15-EMAIL-TEMPLATES](15-EMAIL-TEMPLATES.md) | Customize email templates |
| [16-ENTITY-CUSTOMIZATION](16-ENTITY-CUSTOMIZATION.md) | Extend User, Session entities |
| [17-PASSKEY-WEBAUTHN](17-PASSKEY-WEBAUTHN.md) | Passkeys and WebAuthn setup |
| [18-CONTROLLERS](18-CONTROLLERS.md) | Override and customize controllers |
| [19-CUSTOMIZATION](19-CUSTOMIZATION.md) | Advanced: API versioning, multi-tenant, response formats |

---

## Feature Matrix

| Feature | API Mode | Session Mode | Hybrid Mode |
|---------|----------|--------------|-------------|
| Stateless tokens | ✅ | ❌ | ✅ |
| Cookie sessions | ❌ | ✅ | ✅ |
| Refresh tokens | ✅ | N/A | ✅ |
| CSRF protection | N/A | ✅ | ✅ |
| OAuth providers | ✅ | ✅ | ✅ |
| 2FA (TOTP) | ✅ | ✅ | ✅ |
| Multi-tenant | ✅ | ✅ | ✅ |
| Session tracking | ✅ | ✅ | ✅ |

---

## Console Commands

```bash
# Installation
php bin/console better-auth:install              # Full installation wizard

# Configuration
php bin/console better-auth:configure            # Interactive configuration wizard
php bin/console better-auth:switch-mode          # Switch authentication mode
php bin/console better-auth:generate-config      # Generate config with presets

# Setup
php bin/console better-auth:setup:dependencies   # Install dependencies
php bin/console better-auth:setup:logging        # Configure logging
php bin/console better-auth:config:update        # Update configuration files
```

---

## Support

- **GitHub Issues**: [github.com/MakFly/betterauth-symfony/issues](https://github.com/MakFly/betterauth-symfony/issues)
- **Packagist**: [packagist.org/packages/betterauth/symfony-bundle](https://packagist.org/packages/betterauth/symfony-bundle)

---

Made with ❤️ by the BackToTheFutur Team
