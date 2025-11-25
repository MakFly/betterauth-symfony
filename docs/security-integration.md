# Security Integration Guide

This guide explains how BetterAuth integrates with Symfony Security.

## Auto-Configuration

By default, BetterAuth auto-configures Symfony Security:

```yaml
better_auth:
    security:
        auto_configure: true        # Enable auto-configuration
        firewall_name: 'api'        # Protected API firewall name
        firewall_pattern: '^/api'   # Pattern for protected routes
```

## What Gets Auto-Configured

The bundle prepends the following security configuration:

```yaml
# Auto-generated (you don't need to write this)
security:
    providers:
        better_auth_provider:
            id: BetterAuth\Symfony\Security\BetterAuthUserProvider

    firewalls:
        # Public auth routes (login, register, etc.)
        better_auth_public:
            pattern: ^/auth          # Derived from routing.prefix
            stateless: true
            security: false

        # Protected API firewall
        api:
            pattern: ^/api
            stateless: true
            provider: better_auth_provider
            custom_authenticators:
                - BetterAuth\Symfony\Security\BetterAuthAuthenticator

    access_control:
        - { path: ^/auth, roles: PUBLIC_ACCESS }
        - { path: ^/api, roles: ROLE_USER }
```

## User Override

Your `security.yaml` takes priority over auto-configuration:

```yaml
# config/packages/security.yaml
security:
    firewalls:
        # Your custom firewall - takes precedence
        api:
            pattern: ^/api
            stateless: true
            provider: better_auth_provider
            custom_authenticators:
                - BetterAuth\Symfony\Security\BetterAuthAuthenticator
            # Add your custom settings here
```

## Disable Auto-Configuration

To manually configure security:

```yaml
# config/packages/better_auth.yaml
better_auth:
    security:
        auto_configure: false
```

Then configure security.yaml manually:

```yaml
# config/packages/security.yaml
security:
    providers:
        better_auth_provider:
            id: BetterAuth\Symfony\Security\BetterAuthUserProvider

    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false

        auth:
            pattern: ^/api/v1/auth
            stateless: true
            security: false

        api:
            pattern: ^/api
            stateless: true
            provider: better_auth_provider
            custom_authenticators:
                - BetterAuth\Symfony\Security\BetterAuthAuthenticator

    access_control:
        - { path: ^/api/v1/auth/(login|register|password|magic-link), roles: PUBLIC_ACCESS }
        - { path: ^/api, roles: ROLE_USER }
```

## Custom Authentication Logic

To customize authentication, extend `BetterAuthAuthenticator`:

```php
<?php

namespace App\Security;

use BetterAuth\Symfony\Security\BetterAuthAuthenticator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Exception\AuthenticationException;

class CustomAuthenticator extends BetterAuthAuthenticator
{
    public function supports(Request $request): ?bool
    {
        // Add custom logic
        if ($this->isMaintenanceMode()) {
            return false;
        }

        return parent::supports($request);
    }
}
```

Register in security.yaml:

```yaml
security:
    firewalls:
        api:
            custom_authenticators:
                - App\Security\CustomAuthenticator
```

## Events

BetterAuth dispatches Symfony Security events:

- `BetterAuth\Symfony\Event\AuthenticationSuccessEvent`
- `BetterAuth\Symfony\Event\AuthenticationFailureEvent`
- `BetterAuth\Symfony\Event\TokenCreatedEvent`
- `BetterAuth\Symfony\Event\TokenRefreshedEvent`

Subscribe to events:

```php
<?php

namespace App\EventSubscriber;

use BetterAuth\Symfony\Event\AuthenticationSuccessEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AuthSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            AuthenticationSuccessEvent::class => 'onAuthSuccess',
        ];
    }

    public function onAuthSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();
        // Log login, update last_login, etc.
    }
}
```
