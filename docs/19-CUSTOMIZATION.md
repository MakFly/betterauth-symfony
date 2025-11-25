# Advanced Customization

This guide covers advanced customization scenarios: overriding controllers, customizing response formats, renaming routes, and API versioning.

## Table of Contents

- [Route Customization](#route-customization)
- [API Versioning](#api-versioning)
- [Controller Override](#controller-override)
- [Response Format Customization](#response-format-customization)
- [Use Case: API Migration v1 to v2](#use-case-api-migration-v1-to-v2)
- [Use Case: Multi-Tenant Authentication](#use-case-multi-tenant-authentication)
- [Use Case: Frontend-Specific Response Formats](#use-case-frontend-specific-response-formats)
- [Complete Route Reference](#complete-route-reference)

---

## Route Customization

### Add a Global Prefix

Add a prefix to all BetterAuth routes:

```yaml
# config/routes.yaml

better_auth:
    resource: '@BetterAuthBundle/config/routes.yaml'
    prefix: /api/v1
```

**Result:**
- `/auth/login` → `/api/v1/auth/login`
- `/auth/register` → `/api/v1/auth/register`
- etc.

### Disable Bundle Routes Entirely

To completely replace bundle routes with your own:

```yaml
# config/routes.yaml

# Comment out or remove bundle routes
# better_auth:
#     resource: '@BetterAuthBundle/config/routes.yaml'

# Load your custom controllers instead
app_auth:
    resource:
        path: ../src/Controller/Auth/
        namespace: App\Controller\Auth
    type: attribute
```

### Rename a Specific Route

To rename `/auth/login` to `/api/signin`:

```php
<?php
// src/Controller/Auth/SignInController.php

declare(strict_types=1);

namespace App\Controller\Auth;

use BetterAuth\Core\AuthManager;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use BetterAuth\Symfony\Controller\CredentialsController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class SignInController extends CredentialsController
{
    public function __construct(
        AuthManager $authManager,
        TotpProvider $totpProvider,
    ) {
        parent::__construct($authManager, $totpProvider);
    }

    /**
     * Custom login endpoint with renamed route.
     */
    #[Route('/api/signin', name: 'app_signin', methods: ['POST'])]
    public function signin(Request $request): JsonResponse
    {
        return $this->login($request);
    }

    /**
     * Keep register at a different path too.
     */
    #[Route('/api/signup', name: 'app_signup', methods: ['POST'])]
    public function signup(Request $request): JsonResponse
    {
        return $this->register($request);
    }
}
```

```yaml
# config/routes.yaml

# Disable bundle credentials routes, keep others
better_auth:
    resource: '@BetterAuthBundle/config/routes.yaml'
    exclude: '../src/Controller/CredentialsController.php'

# Load custom signin controller
app_auth:
    resource:
        path: ../src/Controller/Auth/
        namespace: App\Controller\Auth
    type: attribute
```

---

## API Versioning

### URL-Based Versioning (Recommended)

The cleanest approach for API versioning:

```yaml
# config/routes.yaml

# API v2 - New BetterAuth endpoints
api_v2_auth:
    resource: '@BetterAuthBundle/config/routes.yaml'
    prefix: /api/v2

# API v1 - Legacy endpoints (deprecated)
api_v1_auth:
    resource: ../config/routes/legacy_v1.yaml
    prefix: /api/v1
```

**Result:**
- New clients use: `POST /api/v2/auth/login`
- Legacy clients use: `POST /api/v1/auth/login`

### Header-Based Versioning

For clients that specify version via header:

```php
<?php
// src/EventListener/ApiVersionListener.php

declare(strict_types=1);

namespace App\EventListener;

use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::REQUEST, priority: 100)]
class ApiVersionListener
{
    public function __invoke(RequestEvent $event): void
    {
        $request = $event->getRequest();

        // Extract version from Accept header
        // Example: Accept: application/vnd.myapp.v2+json
        $accept = $request->headers->get('Accept', '');

        if (preg_match('/application\/vnd\.myapp\.v(\d+)\+json/', $accept, $matches)) {
            $request->attributes->set('_api_version', (int) $matches[1]);
        } else {
            // Default to v2
            $request->attributes->set('_api_version', 2);
        }
    }
}
```

---

## Controller Override

### Method 1: Inheritance (Simple)

Extend the bundle controller and override specific methods:

```php
<?php
// src/Controller/Auth/CustomCredentialsController.php

declare(strict_types=1);

namespace App\Controller\Auth;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\User;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use BetterAuth\Symfony\Controller\CredentialsController;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route('/auth')]
class CustomCredentialsController extends CredentialsController
{
    public function __construct(
        AuthManager $authManager,
        TotpProvider $totpProvider,
        private readonly LoggerInterface $logger,
        private readonly EventDispatcherInterface $dispatcher,
    ) {
        parent::__construct($authManager, $totpProvider);
    }

    #[Route('/register', name: 'app_register', methods: ['POST'])]
    public function register(Request $request): JsonResponse
    {
        // Pre-processing: custom validation
        $data = $request->toArray();

        if (!$this->isValidEmailDomain($data['email'] ?? '')) {
            return $this->json([
                'error' => 'registration_blocked',
                'message' => 'Registration is only allowed for company emails.',
            ], 403);
        }

        $this->logger->info('Registration attempt', [
            'email' => $data['email'],
            'ip' => $request->getClientIp(),
        ]);

        // Call parent implementation
        $response = parent::register($request);

        // Post-processing: analytics, webhooks, etc.
        if ($response->getStatusCode() === 201) {
            $this->dispatcher->dispatch(new UserRegisteredEvent($data['email']));
        }

        return $response;
    }

    #[Route('/login', name: 'app_login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $this->logger->info('Login attempt', [
            'ip' => $request->getClientIp(),
            'user_agent' => $request->headers->get('User-Agent'),
        ]);

        return parent::login($request);
    }

    private function isValidEmailDomain(string $email): bool
    {
        $allowedDomains = ['company.com', 'company.fr'];
        $domain = substr(strrchr($email, '@'), 1);

        return in_array($domain, $allowedDomains, true);
    }
}
```

### Method 2: Service Decoration (Advanced)

Wrap the original controller without inheritance:

```yaml
# config/services.yaml

services:
    App\Controller\Auth\DecoratedCredentialsController:
        decorates: BetterAuth\Symfony\Controller\CredentialsController
        arguments:
            $inner: '@.inner'
            $logger: '@logger'
            $rateLimiter: '@limiter.login'
```

```php
<?php
// src/Controller/Auth/DecoratedCredentialsController.php

declare(strict_types=1);

namespace App\Controller\Auth;

use BetterAuth\Symfony\Controller\CredentialsController;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\RateLimiter\RateLimiterFactory;

class DecoratedCredentialsController
{
    public function __construct(
        private readonly CredentialsController $inner,
        private readonly LoggerInterface $logger,
        private readonly RateLimiterFactory $rateLimiter,
    ) {
    }

    public function login(Request $request): JsonResponse
    {
        // Rate limiting
        $limiter = $this->rateLimiter->create($request->getClientIp());

        if (!$limiter->consume()->isAccepted()) {
            return new JsonResponse([
                'error' => 'rate_limit_exceeded',
                'message' => 'Too many login attempts. Please try again later.',
                'retry_after' => 60,
            ], 429);
        }

        $this->logger->info('Login attempt', [
            'ip' => $request->getClientIp(),
        ]);

        return $this->inner->login($request);
    }

    public function register(Request $request): JsonResponse
    {
        return $this->inner->register($request);
    }
}
```

### Method 3: From Scratch (Full Control)

Build your own controller using BetterAuth services:

```php
<?php
// src/Controller/Auth/CustomAuthController.php

declare(strict_types=1);

namespace App\Controller\Auth;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\User;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth', name: 'api_auth_')]
class CustomAuthController extends AbstractController
{
    public function __construct(
        private readonly AuthManager $authManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $data = $request->toArray();

        // Custom validation
        if (empty($data['email']) || empty($data['password'])) {
            return $this->json([
                'success' => false,
                'error' => 'Email and password are required.',
            ], 400);
        }

        try {
            $result = $this->authManager->signIn(
                email: $data['email'],
                password: $data['password'],
                ipAddress: $request->getClientIp() ?? '127.0.0.1',
                userAgent: $request->headers->get('User-Agent') ?? 'Unknown',
            );

            $user = $result['user'];

            // Custom response format
            return $this->json([
                'success' => true,
                'data' => [
                    'token' => $result['access_token'],
                    'refreshToken' => $result['refresh_token'],
                    'expiresAt' => date('c', time() + ($result['expires_in'] ?? 3600)),
                ],
                'user' => $this->formatUser($user),
            ]);
        } catch (\Exception $e) {
            $this->logger->warning('Login failed', [
                'email' => $data['email'],
                'error' => $e->getMessage(),
            ]);

            return $this->json([
                'success' => false,
                'error' => 'Invalid credentials.',
            ], 401);
        }
    }

    private function formatUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'displayName' => $user->getName(),
            'verified' => $user->isEmailVerified(),
            'memberSince' => $user->getCreatedAt()->format('Y-m-d'),
        ];
    }
}
```

---

## Response Format Customization

### Understanding AuthResponseTrait

The bundle uses `AuthResponseTrait` with two main methods:

```php
// Default formatUser() output
[
    'id' => 'uuid-here',
    'email' => 'user@example.com',
    'name' => 'John Doe',
    'emailVerified' => true,
    'createdAt' => '2024-01-15T10:30:00+00:00',
    'updatedAt' => '2024-01-15T10:30:00+00:00',
]

// Default formatAuthResponse() output
[
    'access_token' => 'v4.local.xxx...',
    'refresh_token' => 'xxx...',
    'expires_in' => 3600,
    'token_type' => 'Bearer',
    'user' => [...],
]
```

### Custom Response Trait

Create your own trait to override response formatting:

```php
<?php
// src/Controller/Trait/CustomAuthResponseTrait.php

declare(strict_types=1);

namespace App\Controller\Trait;

use BetterAuth\Core\Entities\User;

trait CustomAuthResponseTrait
{
    protected function formatUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'profile' => [
                'displayName' => $user->getName(),
                'avatar' => $user->getAvatar(),
                'initials' => $this->getInitials($user->getName()),
            ],
            'status' => [
                'verified' => $user->isEmailVerified(),
                'active' => true,
            ],
            'timestamps' => [
                'createdAt' => $user->getCreatedAt()->format('c'),
                'updatedAt' => $user->getUpdatedAt()->format('c'),
            ],
        ];
    }

    protected function formatAuthResponse(array $result, User $user): array
    {
        $expiresIn = $result['expires_in'] ?? 3600;

        return [
            'auth' => [
                'accessToken' => $result['access_token'],
                'refreshToken' => $result['refresh_token'],
                'tokenType' => 'Bearer',
                'expiresIn' => $expiresIn,
                'expiresAt' => date('c', time() + $expiresIn),
            ],
            'user' => $this->formatUser($user),
            'meta' => [
                'serverTime' => date('c'),
                'apiVersion' => 'v2',
            ],
        ];
    }

    private function getInitials(?string $name): string
    {
        if (empty($name)) {
            return '??';
        }

        $parts = explode(' ', $name);
        $initials = '';

        foreach (array_slice($parts, 0, 2) as $part) {
            $initials .= strtoupper(substr($part, 0, 1));
        }

        return $initials;
    }
}
```

Use it in your controller:

```php
<?php
// src/Controller/Auth/V2CredentialsController.php

declare(strict_types=1);

namespace App\Controller\Auth;

use App\Controller\Trait\CustomAuthResponseTrait;
use BetterAuth\Core\AuthManager;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use BetterAuth\Symfony\Controller\CredentialsController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v2/auth')]
class V2CredentialsController extends CredentialsController
{
    use CustomAuthResponseTrait;

    public function __construct(
        AuthManager $authManager,
        TotpProvider $totpProvider,
    ) {
        parent::__construct($authManager, $totpProvider);
    }
}
```

---

## Use Case: API Migration v1 to v2

Complete example for migrating from legacy API to BetterAuth.

### Directory Structure

```
src/Controller/
├── Auth/
│   ├── V1/
│   │   └── LegacyAuthController.php    # Deprecated, for backwards compatibility
│   └── V2/
│       ├── AuthController.php          # New BetterAuth-based
│       └── Trait/
│           └── V2ResponseTrait.php
config/
├── routes.yaml
└── packages/
    └── security.yaml
```

### Routes Configuration

```yaml
# config/routes.yaml

# API v2 - New endpoints (recommended)
api_v2_auth:
    resource:
        path: ../src/Controller/Auth/V2/
        namespace: App\Controller\Auth\V2
    type: attribute
    prefix: /api/v2

# API v1 - Legacy endpoints (deprecated, remove after migration)
api_v1_auth:
    resource:
        path: ../src/Controller/Auth/V1/
        namespace: App\Controller\Auth\V1
    type: attribute
    prefix: /api/v1

# Other app routes
app:
    resource:
        path: ../src/Controller/
        namespace: App\Controller
        exclude: ../src/Controller/Auth/
    type: attribute
```

### Security Configuration

```yaml
# config/packages/security.yaml

security:
    providers:
        better_auth:
            id: BetterAuth\Symfony\Security\BetterAuthUserProvider

    firewalls:
        dev:
            pattern: ^/(_(profiler|wdt)|css|images|js)/
            security: false

        # API v2 firewall
        api_v2:
            pattern: ^/api/v2
            stateless: true
            provider: better_auth
            custom_authenticators:
                - BetterAuth\Symfony\Security\BetterAuthAuthenticator

        # API v1 firewall (legacy)
        api_v1:
            pattern: ^/api/v1
            stateless: true
            provider: better_auth
            custom_authenticators:
                - BetterAuth\Symfony\Security\BetterAuthAuthenticator

    access_control:
        # Public endpoints
        - { path: ^/api/v[12]/auth/(login|register|password), roles: PUBLIC_ACCESS }
        # Protected endpoints
        - { path: ^/api/v[12], roles: ROLE_USER }
```

### V1 Legacy Controller (Backwards Compatibility)

```php
<?php
// src/Controller/Auth/V1/LegacyAuthController.php

declare(strict_types=1);

namespace App\Controller\Auth\V1;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\User;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

/**
 * @deprecated Use /api/v2/auth endpoints instead.
 */
#[Route('/auth', name: 'api_v1_auth_')]
class LegacyAuthController extends AbstractController
{
    public function __construct(
        private readonly AuthManager $authManager,
    ) {
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(Request $request): JsonResponse
    {
        $response = new JsonResponse();
        $response->headers->set('X-Deprecated', 'true');
        $response->headers->set('X-Deprecated-Message', 'Use /api/v2/auth/login instead');

        $data = $request->toArray();

        try {
            $result = $this->authManager->signIn(
                email: $data['email'] ?? $data['username'] ?? '',
                password: $data['password'] ?? '',
                ipAddress: $request->getClientIp() ?? '127.0.0.1',
                userAgent: $request->headers->get('User-Agent') ?? 'Unknown',
            );

            // V1 response format (legacy)
            return $this->json([
                'status' => 'success',
                'token' => $result['access_token'],
                'user' => [
                    'id' => $result['user']->getId(),
                    'username' => $result['user']->getEmail(),
                    'email' => $result['user']->getEmail(),
                ],
            ], headers: [
                'X-Deprecated' => 'true',
                'X-Deprecated-Message' => 'Use /api/v2/auth/login instead',
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'status' => 'error',
                'message' => 'Invalid credentials',
            ], 401, [
                'X-Deprecated' => 'true',
            ]);
        }
    }
}
```

### V2 New Controller

```php
<?php
// src/Controller/Auth/V2/AuthController.php

declare(strict_types=1);

namespace App\Controller\Auth\V2;

use BetterAuth\Core\AuthManager;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use BetterAuth\Symfony\Controller\CredentialsController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/auth', name: 'api_v2_auth_')]
class AuthController extends CredentialsController
{
    public function __construct(
        AuthManager $authManager,
        TotpProvider $totpProvider,
    ) {
        parent::__construct($authManager, $totpProvider);
    }

    // Inherits all methods from CredentialsController:
    // - POST /auth/register
    // - POST /auth/login
    // - POST /auth/login/2fa
}
```

---

## Use Case: Multi-Tenant Authentication

Routes prefixed by tenant identifier.

### Routes Configuration

```yaml
# config/routes.yaml

# Tenant-specific auth routes
tenant_auth:
    resource:
        path: ../src/Controller/Tenant/
        namespace: App\Controller\Tenant
    type: attribute
```

### Tenant Auth Controller

```php
<?php
// src/Controller/Tenant/TenantAuthController.php

declare(strict_types=1);

namespace App\Controller\Tenant;

use App\Service\TenantResolver;
use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\User;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/{tenant}/auth', name: 'tenant_auth_', requirements: ['tenant' => '[a-z0-9-]+'])]
class TenantAuthController extends AbstractController
{
    public function __construct(
        private readonly AuthManager $authManager,
        private readonly TotpProvider $totpProvider,
        private readonly TenantResolver $tenantResolver,
    ) {
    }

    #[Route('/login', name: 'login', methods: ['POST'])]
    public function login(string $tenant, Request $request): JsonResponse
    {
        // Resolve and validate tenant
        $tenantConfig = $this->tenantResolver->resolve($tenant);

        if ($tenantConfig === null) {
            throw new NotFoundHttpException("Tenant '$tenant' not found.");
        }

        $data = $request->toArray();

        try {
            $result = $this->authManager->signIn(
                email: $data['email'],
                password: $data['password'],
                ipAddress: $request->getClientIp() ?? '127.0.0.1',
                userAgent: $request->headers->get('User-Agent') ?? 'Unknown',
            );

            $user = $result['user'];

            // Verify user belongs to this tenant
            if (!$this->userBelongsToTenant($user, $tenant)) {
                return $this->json([
                    'error' => 'access_denied',
                    'message' => 'User does not belong to this tenant.',
                ], 403);
            }

            return $this->json([
                'tenant' => $tenant,
                'access_token' => $result['access_token'],
                'refresh_token' => $result['refresh_token'],
                'expires_in' => $result['expires_in'] ?? 3600,
                'token_type' => 'Bearer',
                'user' => $this->formatUser($user, $tenant),
            ]);
        } catch (\Exception $e) {
            return $this->json([
                'tenant' => $tenant,
                'error' => 'authentication_failed',
                'message' => 'Invalid credentials.',
            ], 401);
        }
    }

    #[Route('/register', name: 'register', methods: ['POST'])]
    public function register(string $tenant, Request $request): JsonResponse
    {
        $tenantConfig = $this->tenantResolver->resolve($tenant);

        if ($tenantConfig === null) {
            throw new NotFoundHttpException("Tenant '$tenant' not found.");
        }

        // Check if registration is allowed for this tenant
        if (!$tenantConfig['allow_registration']) {
            return $this->json([
                'error' => 'registration_disabled',
                'message' => 'Registration is disabled for this tenant.',
            ], 403);
        }

        $data = $request->toArray();

        try {
            $result = $this->authManager->signUp(
                email: $data['email'],
                password: $data['password'],
                name: $data['name'] ?? null,
                metadata: [
                    'tenant' => $tenant,
                    'tenant_role' => $tenantConfig['default_role'] ?? 'member',
                ],
            );

            $user = $result['user'];

            // Auto sign-in after registration
            $loginResult = $this->authManager->signIn(
                email: $data['email'],
                password: $data['password'],
                ipAddress: $request->getClientIp() ?? '127.0.0.1',
                userAgent: $request->headers->get('User-Agent') ?? 'Unknown',
            );

            return $this->json([
                'tenant' => $tenant,
                'access_token' => $loginResult['access_token'],
                'refresh_token' => $loginResult['refresh_token'],
                'expires_in' => $loginResult['expires_in'] ?? 3600,
                'token_type' => 'Bearer',
                'user' => $this->formatUser($user, $tenant),
            ], 201);
        } catch (\Exception $e) {
            return $this->json([
                'tenant' => $tenant,
                'error' => 'registration_failed',
                'message' => $e->getMessage(),
            ], 400);
        }
    }

    private function userBelongsToTenant(User $user, string $tenant): bool
    {
        $metadata = $user->getMetadata();

        return isset($metadata['tenant']) && $metadata['tenant'] === $tenant;
    }

    private function formatUser(User $user, string $tenant): array
    {
        $metadata = $user->getMetadata();

        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'emailVerified' => $user->isEmailVerified(),
            'tenant' => [
                'id' => $tenant,
                'role' => $metadata['tenant_role'] ?? 'member',
            ],
        ];
    }
}
```

### Tenant Resolver Service

```php
<?php
// src/Service/TenantResolver.php

declare(strict_types=1);

namespace App\Service;

class TenantResolver
{
    private array $tenants = [
        'acme-corp' => [
            'name' => 'ACME Corporation',
            'allow_registration' => true,
            'default_role' => 'member',
        ],
        'beta-inc' => [
            'name' => 'Beta Inc',
            'allow_registration' => false,
            'default_role' => 'viewer',
        ],
    ];

    public function resolve(string $tenant): ?array
    {
        return $this->tenants[$tenant] ?? null;
    }

    public function getAllTenants(): array
    {
        return array_keys($this->tenants);
    }
}
```

**Usage:**
```bash
# Login to ACME Corp tenant
curl -X POST https://api.example.com/acme-corp/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email": "user@acme.com", "password": "secret"}'

# Register on Beta Inc tenant (if allowed)
curl -X POST https://api.example.com/beta-inc/auth/register \
  -H "Content-Type: application/json" \
  -d '{"email": "new@beta.com", "password": "secret", "name": "New User"}'
```

---

## Use Case: Frontend-Specific Response Formats

### React Admin Format

React Admin expects `{ data: {...} }` format:

```php
<?php
// src/Controller/Trait/ReactAdminResponseTrait.php

declare(strict_types=1);

namespace App\Controller\Trait;

use BetterAuth\Core\Entities\User;

trait ReactAdminResponseTrait
{
    protected function formatUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'fullName' => $user->getName(),
            'avatar' => $user->getAvatar(),
        ];
    }

    protected function formatAuthResponse(array $result, User $user): array
    {
        return [
            'data' => [
                'user' => $this->formatUser($user),
                'permissions' => $this->getUserPermissions($user),
            ],
            'token' => $result['access_token'],
        ];
    }

    private function getUserPermissions(User $user): array
    {
        // Map your roles to React Admin permissions
        return ['admin', 'user'];
    }
}
```

```php
<?php
// src/Controller/Admin/ReactAdminAuthController.php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Controller\Trait\ReactAdminResponseTrait;
use BetterAuth\Core\AuthManager;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use BetterAuth\Symfony\Controller\CredentialsController;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/auth', name: 'admin_auth_')]
class ReactAdminAuthController extends CredentialsController
{
    use ReactAdminResponseTrait;

    public function __construct(
        AuthManager $authManager,
        TotpProvider $totpProvider,
    ) {
        parent::__construct($authManager, $totpProvider);
    }
}
```

### Nuxt Auth Format

Nuxt Auth module expects specific format:

```php
<?php
// src/Controller/Trait/NuxtAuthResponseTrait.php

declare(strict_types=1);

namespace App\Controller\Trait;

use BetterAuth\Core\Entities\User;

trait NuxtAuthResponseTrait
{
    protected function formatUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'name' => $user->getName(),
            'picture' => $user->getAvatar(),
            'email_verified' => $user->isEmailVerified(),
        ];
    }

    protected function formatAuthResponse(array $result, User $user): array
    {
        $expiresIn = $result['expires_in'] ?? 3600;

        return [
            'token' => [
                'accessToken' => $result['access_token'],
                'refreshToken' => $result['refresh_token'],
                'tokenType' => 'Bearer',
                'expiresIn' => $expiresIn,
                'expiresAt' => time() + $expiresIn,
            ],
            'user' => $this->formatUser($user),
        ];
    }
}
```

```php
<?php
// src/Controller/Api/NuxtAuthController.php

declare(strict_types=1);

namespace App\Controller\Api;

use App\Controller\Trait\NuxtAuthResponseTrait;
use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\User;
use BetterAuth\Providers\TotpProvider\TotpProvider;
use BetterAuth\Symfony\Controller\CredentialsController;
use BetterAuth\Symfony\Security\Attribute\CurrentUser;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/auth', name: 'nuxt_auth_')]
class NuxtAuthController extends CredentialsController
{
    use NuxtAuthResponseTrait;

    public function __construct(
        AuthManager $authManager,
        TotpProvider $totpProvider,
    ) {
        parent::__construct($authManager, $totpProvider);
    }

    /**
     * Nuxt Auth user endpoint.
     */
    #[Route('/user', name: 'user', methods: ['GET'])]
    public function user(#[CurrentUser] User $user): JsonResponse
    {
        return $this->json([
            'user' => $this->formatUser($user),
        ]);
    }
}
```

---

## Complete Route Reference

All bundle routes with their controllers and descriptions:

| Route | Name | Method | Controller | Description |
|-------|------|--------|------------|-------------|
| `/auth/register` | `better_auth_register` | POST | CredentialsController | Create new user account |
| `/auth/login` | `better_auth_login` | POST | CredentialsController | Authenticate with email/password |
| `/auth/login/2fa` | `better_auth_login_2fa` | POST | CredentialsController | Complete login with 2FA code |
| `/auth/me` | `better_auth_me` | GET | TokenController | Get current authenticated user |
| `/auth/refresh` | `better_auth_refresh` | POST | TokenController | Refresh access token |
| `/auth/logout` | `better_auth_logout` | POST | TokenController | Invalidate current session |
| `/auth/revoke-all` | `better_auth_revoke_all` | POST | TokenController | Revoke all user sessions |
| `/auth/sessions` | `better_auth_sessions_list` | GET | SessionController | List all active sessions |
| `/auth/sessions/{sessionId}` | `better_auth_sessions_revoke` | DELETE | SessionController | Revoke specific session |
| `/auth/oauth/providers` | `better_auth_oauth_providers` | GET | OAuthController | List available OAuth providers |
| `/auth/oauth/{provider}` | `better_auth_oauth_redirect` | GET | OAuthController | Redirect to OAuth provider |
| `/auth/oauth/{provider}/url` | `better_auth_oauth_url` | GET | OAuthController | Get OAuth URL (JSON) |
| `/auth/oauth/{provider}/callback` | `better_auth_oauth_callback` | GET | OAuthController | Handle OAuth callback |
| `/auth/2fa/setup` | `better_auth_2fa_setup` | POST | TwoFactorController | Generate TOTP secret and QR |
| `/auth/2fa/validate` | `better_auth_2fa_validate` | POST | TwoFactorController | Validate and enable 2FA |
| `/auth/2fa/verify` | `better_auth_2fa_verify` | POST | TwoFactorController | Verify 2FA code |
| `/auth/2fa/disable` | `better_auth_2fa_disable` | POST | TwoFactorController | Disable 2FA |
| `/auth/2fa/backup-codes/regenerate` | `better_auth_2fa_regenerate_backup_codes` | POST | TwoFactorController | Regenerate backup codes |
| `/auth/2fa/status` | `better_auth_2fa_status` | GET | TwoFactorController | Get 2FA status |
| `/auth/2fa/reset` | `better_auth_2fa_reset` | POST | TwoFactorController | Reset 2FA with backup code |
| `/auth/password/forgot` | `better_auth_password_forgot` | POST | PasswordResetController | Request password reset |
| `/auth/password/reset` | `better_auth_password_reset` | POST | PasswordResetController | Reset password with token |
| `/auth/password/verify-token` | `better_auth_password_verify_token` | POST | PasswordResetController | Verify reset token validity |
| `/auth/email/send-verification` | `better_auth_email_send_verification` | POST | EmailVerificationController | Send verification email |
| `/auth/email/verify` | `better_auth_email_verify` | POST | EmailVerificationController | Verify email with token |
| `/auth/email/verification-status` | `better_auth_email_verification_status` | GET | EmailVerificationController | Get verification status |
| `/auth/magic-link/send` | `better_auth_magic_link_send` | POST | MagicLinkController | Send magic link email |
| `/auth/magic-link/verify` | `better_auth_magic_link_verify` | POST | MagicLinkController | Verify magic link |
| `/auth/magic-link/verify/{token}` | `better_auth_magic_link_verify_get` | GET | MagicLinkController | Verify magic link (GET) |
| `/auth/guest/create` | `better_auth_guest_create` | POST | GuestSessionController | Create guest session |
| `/auth/guest/{token}` | `better_auth_guest_get` | GET | GuestSessionController | Get guest session |
| `/auth/guest/convert` | `better_auth_guest_convert` | POST | GuestSessionController | Convert guest to user |
| `/auth/guest/{token}` | `better_auth_guest_delete` | DELETE | GuestSessionController | Delete guest session |

---

## See Also

- [Controllers](18-CONTROLLERS.md) - Basic controller documentation
- [API Reference](09-API-REFERENCE.md) - Detailed endpoint documentation
- [Events](08-EVENTS.md) - Subscribe to authentication events
- [Security](11-SECURITY.md) - Security best practices
