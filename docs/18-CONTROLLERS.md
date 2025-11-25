# Controllers

BetterAuth Symfony provides ready-to-use controllers for all authentication endpoints. These controllers are automatically registered when you install the bundle.

## Table of Contents

- [Available Controllers](#available-controllers)
- [Route Loading](#route-loading)
- [Customizing Controllers](#customizing-controllers)
- [Controller Architecture](#controller-architecture)
- [CurrentUser Attribute](#currentuser-attribute)

---

## Available Controllers

| Controller | Prefix | Description |
|------------|--------|-------------|
| `CredentialsController` | `/auth` | Register, login, login with 2FA |
| `TokenController` | `/auth/token` | Me, refresh, logout, revoke all |
| `SessionController` | `/auth/sessions` | List sessions, revoke session |
| `OAuthController` | `/auth/oauth` | OAuth providers, redirect, callback |
| `TwoFactorController` | `/auth/2fa` | Setup, validate, verify, disable |
| `PasswordResetController` | `/auth/password` | Forgot password, reset, verify token |
| `EmailVerificationController` | `/auth/email` | Send verification, verify, status |
| `MagicLinkController` | `/auth/magic-link` | Send magic link, verify |
| `GuestSessionController` | `/auth/guest` | Create, get, convert, delete guest |

---

## Route Loading

### Automatic Loading (Default)

Routes are automatically loaded when the bundle is installed. In your `config/routes.yaml`:

```yaml
better_auth:
    resource: '@BetterAuthBundle/config/routes.yaml'
```

### View Available Routes

```bash
php bin/console debug:router | grep better_auth
```

Output:
```
better_auth_register                  POST    /auth/register
better_auth_login                     POST    /auth/login
better_auth_login_2fa                 POST    /auth/login/2fa
better_auth_me                        GET     /auth/token/me
better_auth_refresh                   POST    /auth/token/refresh
better_auth_logout                    POST    /auth/token/logout
better_auth_oauth_providers           GET     /auth/oauth/providers
better_auth_oauth_redirect            GET     /auth/oauth/{provider}
better_auth_oauth_callback            GET     /auth/oauth/{provider}/callback
better_auth_2fa_setup                 POST    /auth/2fa/setup
better_auth_2fa_verify                POST    /auth/2fa/verify
# ... more routes
```

---

## Customizing Controllers

### Option 1: Extend the Bundle Controller

Create your own controller that extends the bundle controller:

```php
<?php
// src/Controller/CustomCredentialsController.php

namespace App\Controller;

use BetterAuth\Symfony\Controller\CredentialsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth')]
class CustomCredentialsController extends CredentialsController
{
    #[Route('/register', name: 'app_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        // Custom logic before registration
        $this->logger?->info('Custom registration started', [
            'ip' => $request->getClientIp(),
        ]);

        // Call parent implementation
        $response = parent::register($request);

        // Custom logic after registration
        // e.g., send to analytics, trigger webhook, etc.

        return $response;
    }
}
```

Then disable the bundle route and use your own:

```yaml
# config/routes.yaml

# Comment out bundle routes for specific controllers
# better_auth:
#     resource: '@BetterAuthBundle/config/routes.yaml'

# Load your custom controllers
controllers:
    resource:
        path: ../src/Controller/
        namespace: App\Controller
    type: attribute
```

### Option 2: Decorate the Service

Use Symfony's service decoration to wrap the bundle controller:

```yaml
# config/services.yaml
services:
    App\Controller\CustomCredentialsController:
        decorates: BetterAuth\Symfony\Controller\CredentialsController
        arguments:
            $inner: '@.inner'
```

```php
<?php
// src/Controller/CustomCredentialsController.php

namespace App\Controller;

use BetterAuth\Symfony\Controller\CredentialsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

class CustomCredentialsController
{
    public function __construct(
        private readonly CredentialsController $inner,
    ) {}

    public function register(Request $request): JsonResponse
    {
        // Pre-processing
        $this->validateCustomRules($request);

        // Call original
        $response = $this->inner->register($request);

        // Post-processing
        $this->notifyAdmin($request);

        return $response;
    }

    private function validateCustomRules(Request $request): void
    {
        // Custom validation
    }

    private function notifyAdmin(Request $request): void
    {
        // Notify admin of new registration
    }
}
```

### Option 3: Use Events

Subscribe to events dispatched by the authentication system:

```php
<?php
// src/EventSubscriber/AuthSubscriber.php

namespace App\EventSubscriber;

use BetterAuth\Symfony\Event\UserRegisteredEvent;
use BetterAuth\Symfony\Event\UserLoggedInEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AuthSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            UserRegisteredEvent::class => 'onUserRegistered',
            UserLoggedInEvent::class => 'onUserLoggedIn',
        ];
    }

    public function onUserRegistered(UserRegisteredEvent $event): void
    {
        $user = $event->getUser();
        // Send welcome email, create profile, etc.
    }

    public function onUserLoggedIn(UserLoggedInEvent $event): void
    {
        $user = $event->getUser();
        $session = $event->getSession();
        // Log activity, update last login, etc.
    }
}
```

---

## Controller Architecture

### Directory Structure

```
betterauth-symfony/src/Controller/
├── Trait/
│   └── AuthResponseTrait.php      # Shared response helpers
├── CredentialsController.php      # register, login, login/2fa
├── TokenController.php            # me, refresh, logout, revoke-all
├── SessionController.php          # sessions list, revoke
├── OAuthController.php            # oauth/*, callback, providers
├── TwoFactorController.php        # 2fa setup, validate, verify, disable
├── PasswordResetController.php    # forgot, reset, verify-token
├── EmailVerificationController.php # send-verification, verify, status
├── MagicLinkController.php        # send, verify
└── GuestSessionController.php     # create, get, convert, delete
```

### AuthResponseTrait

All controllers use `AuthResponseTrait` for consistent responses:

```php
trait AuthResponseTrait
{
    protected function successResponse(array $data, int $status = 200): JsonResponse;
    protected function errorResponse(string $message, int $status = 400, array $errors = []): JsonResponse;
    protected function validationErrorResponse(array $errors): JsonResponse;
}
```

---

## CurrentUser Attribute

Inject the authenticated user directly into your controller actions:

```php
<?php

namespace App\Controller;

use BetterAuth\Core\Entity\User;
use BetterAuth\Symfony\Security\Attribute\CurrentUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

class ProfileController extends AbstractController
{
    #[Route('/api/profile', methods: ['GET'])]
    public function profile(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json([
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'emailVerified' => $user->isEmailVerified(),
        ]);
    }

    #[Route('/api/profile', methods: ['PUT'])]
    public function update(
        #[CurrentUser] User $user,
        Request $request,
    ): JsonResponse {
        // Update user profile
        $data = $request->toArray();

        // ... update logic

        return $this->json(['message' => 'Profile updated']);
    }
}
```

### How It Works

1. `CurrentUserResolver` intercepts requests with `#[CurrentUser]` attribute
2. Extracts the JWT token from `Authorization: Bearer <token>` header
3. Validates the token and fetches the associated user
4. Injects the `User` entity into the controller action

### Error Handling

If authentication fails, an `AuthenticationException` is thrown and handled by `AuthExceptionListener`:

```json
{
    "error": "authentication_error",
    "message": "Invalid or expired token"
}
```

---

## Full Endpoint Reference

### Credentials

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/register` | Register new user |
| POST | `/auth/login` | Login with email/password |
| POST | `/auth/login/2fa` | Login with 2FA code |

### Token

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/auth/token/me` | Get current user |
| POST | `/auth/token/refresh` | Refresh access token |
| POST | `/auth/token/logout` | Logout (revoke token) |
| POST | `/auth/token/revoke-all` | Revoke all sessions |

### Session

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/auth/sessions` | List all sessions |
| DELETE | `/auth/sessions/{id}` | Revoke specific session |

### OAuth

| Method | Endpoint | Description |
|--------|----------|-------------|
| GET | `/auth/oauth/providers` | List available providers |
| GET | `/auth/oauth/{provider}` | Redirect to OAuth provider |
| GET | `/auth/oauth/{provider}/url` | Get OAuth URL (JSON) |
| GET | `/auth/oauth/{provider}/callback` | OAuth callback handler |

### Two-Factor Authentication

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/2fa/setup` | Generate TOTP secret |
| POST | `/auth/2fa/verify` | Verify and enable 2FA |
| POST | `/auth/2fa/validate` | Validate 2FA code |
| POST | `/auth/2fa/disable` | Disable 2FA |

### Password Reset

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/password/forgot` | Request password reset |
| POST | `/auth/password/reset` | Reset password with token |
| POST | `/auth/password/verify-token` | Verify reset token |

### Email Verification

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/email/send-verification` | Send verification email |
| POST | `/auth/email/verify` | Verify email with token |
| GET | `/auth/email/verification-status` | Get verification status |

### Magic Link

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/magic-link/send` | Send magic link email |
| POST | `/auth/magic-link/verify` | Verify magic link |
| GET | `/auth/magic-link/verify/{token}` | Verify magic link (GET) |

### Guest Session

| Method | Endpoint | Description |
|--------|----------|-------------|
| POST | `/auth/guest/create` | Create guest session |
| GET | `/auth/guest/{token}` | Get guest session |
| POST | `/auth/guest/convert` | Convert guest to user |
| DELETE | `/auth/guest/{token}` | Delete guest session |

---

## See Also

- [API Reference](09-API-REFERENCE.md) - Detailed API documentation with examples
- [Events](08-EVENTS.md) - All events you can subscribe to
- [Error Handling](10-ERROR-HANDLING.md) - Exception handling and error responses
