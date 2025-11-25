# Hybrid Mode Guide

This guide explains how to use hybrid mode - mixing bundle controllers with custom ones.

## Overview

Hybrid mode allows you to:
- Use bundle controllers for some features
- Disable specific features entirely
- Override specific controllers with custom implementations
- Mix and match per endpoint

## Configuration

### Enable/Disable by Group

```yaml
better_auth:
    controllers:
        groups:
            credentials:
                enabled: true          # Use bundle controller

            oauth:
                enabled: true
                class: App\Controller\CustomOAuthController  # Override

            two_factor:
                enabled: false         # Disable entirely

            guest_session:
                enabled: false         # Disable entirely
```

### Enable/Disable by Endpoint

```yaml
better_auth:
    controllers:
        endpoints:
            register:
                enabled: true
                path: '/signup'        # Custom path

            magic_link_send:
                enabled: false         # Disable specific endpoint

            login:
                enabled: true
```

## Example Scenarios

### Scenario 1: Basic Auth Only

Only enable login/register, disable everything else:

```yaml
better_auth:
    routing:
        prefix: '/api/v1/auth'

    controllers:
        groups:
            credentials:
                enabled: true
            token:
                enabled: true
            session:
                enabled: true

            # Disable optional features
            oauth:
                enabled: false
            two_factor:
                enabled: false
            password_reset:
                enabled: false
            email_verification:
                enabled: false
            magic_link:
                enabled: false
            guest_session:
                enabled: false
```

### Scenario 2: Custom OAuth + Bundle Auth

```yaml
better_auth:
    routing:
        prefix: '/api/v1/auth'

    controllers:
        groups:
            # Use bundle controllers
            credentials:
                enabled: true
            token:
                enabled: true
            session:
                enabled: true
            two_factor:
                enabled: true

            # Custom OAuth implementation
            oauth:
                enabled: true
                class: App\Controller\Auth\CustomOAuthController

            # Disable unused
            magic_link:
                enabled: false
            guest_session:
                enabled: false
```

### Scenario 3: Full Custom with Bundle Services

Disable all bundle controllers, use only services:

```yaml
better_auth:
    controllers:
        enabled: false  # Master switch - disable all

    security:
        auto_configure: true  # But still configure security
```

Then create your own controllers using bundle services:

```php
<?php

namespace App\Controller\Auth;

use BetterAuth\Core\AuthManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/auth')]
class AuthController extends AbstractController
{
    public function __construct(
        private readonly AuthManager $authManager,
    ) {}

    #[Route('/login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = $request->toArray();

        // Your custom logic using BetterAuth services
        $result = $this->authManager->signIn(
            $data['email'],
            $data['password'],
            $request->getClientIp() ?? '127.0.0.1',
            $request->headers->get('User-Agent') ?? 'Unknown'
        );

        // Your custom response format
        return $this->json([
            'token' => $result['access_token'],
            'user' => [
                'id' => $result['user']->getId(),
                'email' => $result['user']->getEmail(),
            ],
        ]);
    }
}
```

## Combining with Custom Routes

```yaml
# config/routes.yaml

# Bundle routes (respects controllers config)
better_auth:
    resource: .
    type: better_auth

# Additional custom routes
app_auth:
    resource: '../src/Controller/Auth/'
    type: attribute
    prefix: '/api/v1/auth'
```

## Checking Available Endpoints

After configuration, check available routes:

```bash
php bin/console debug:router | grep auth
```
