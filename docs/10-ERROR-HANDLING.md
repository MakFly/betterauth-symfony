# Error Handling

Guide to handling errors and exceptions in BetterAuth.

## Error Response Format

All errors follow a consistent JSON format:

```json
{
  "error": "Error message here"
}
```

---

## HTTP Status Codes

| Code | Name | Description |
|------|------|-------------|
| 200 | OK | Success |
| 201 | Created | Resource created |
| 400 | Bad Request | Invalid input |
| 401 | Unauthorized | Authentication required |
| 403 | Forbidden | Access denied |
| 404 | Not Found | Resource not found |
| 409 | Conflict | Resource conflict |
| 422 | Unprocessable Entity | Validation failed |
| 429 | Too Many Requests | Rate limit exceeded |
| 500 | Internal Server Error | Server error |

---

## Common Errors

### Authentication Errors (401)

| Error | Cause | Solution |
|-------|-------|----------|
| "No token provided" | Missing Authorization header | Add Bearer token |
| "Invalid token" | Token signature invalid | Get new token |
| "Token expired" | Token has expired | Refresh token |
| "Invalid credentials" | Wrong email/password | Check credentials |

### Validation Errors (400)

| Error | Cause | Solution |
|-------|-------|----------|
| "Email and password are required" | Missing fields | Provide all fields |
| "Invalid email format" | Email validation failed | Use valid email |
| "Password too short" | Password < 8 chars | Use stronger password |

### Registration Errors

| Error | Cause | Solution |
|-------|-------|----------|
| "User already exists" | Email already registered | Login or use another email |
| "Invalid email format" | Email validation failed | Use valid email |

### 2FA Errors

| Error | Cause | Solution |
|-------|-------|----------|
| "Invalid 2FA code" | Wrong TOTP code | Enter correct code |
| "2FA not enabled" | User hasn't set up 2FA | Setup 2FA first |
| "Code expired" | TOTP code window passed | Get new code |

---

## Exception Classes

### BetterAuthException

Base exception for all BetterAuth errors.

```php
use BetterAuth\Core\Exception\BetterAuthException;

try {
    $this->authManager->signIn($email, $password, $ip, $userAgent);
} catch (BetterAuthException $e) {
    return $this->json(['error' => $e->getMessage()], $e->getCode());
}
```

### AuthenticationException

Thrown when authentication fails.

```php
use BetterAuth\Core\Exception\AuthenticationException;

try {
    $this->authManager->signIn($email, $password, $ip, $userAgent);
} catch (AuthenticationException $e) {
    // Log failed attempt
    $this->logger->warning('Login failed', ['email' => $email]);
    return $this->json(['error' => $e->getMessage()], 401);
}
```

### TokenException

Thrown for token-related errors.

```php
use BetterAuth\Core\Exception\TokenException;

try {
    $user = $this->authManager->getCurrentUser($token);
} catch (TokenException $e) {
    return $this->json(['error' => 'Invalid token'], 401);
}
```

### ValidationException

Thrown for validation errors.

```php
use BetterAuth\Core\Exception\ValidationException;

try {
    $user = $this->authManager->signUp($email, $password, $data);
} catch (ValidationException $e) {
    return $this->json(['error' => $e->getMessage()], 400);
}
```

---

## Custom Error Handler

Create a custom exception listener:

```php
<?php

namespace App\EventListener;

use BetterAuth\Core\Exception\BetterAuthException;
use BetterAuth\Core\Exception\AuthenticationException;
use BetterAuth\Core\Exception\TokenException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Psr\Log\LoggerInterface;

class ExceptionListener
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public function onKernelException(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $request = $event->getRequest();

        // Only handle JSON API requests
        if (!str_contains($request->getPathInfo(), '/auth')) {
            return;
        }

        $response = $this->createErrorResponse($exception);
        $event->setResponse($response);
    }

    private function createErrorResponse(\Throwable $exception): JsonResponse
    {
        $statusCode = 500;
        $message = 'An error occurred';

        if ($exception instanceof AuthenticationException) {
            $statusCode = 401;
            $message = $exception->getMessage();
        } elseif ($exception instanceof TokenException) {
            $statusCode = 401;
            $message = 'Invalid or expired token';
        } elseif ($exception instanceof BetterAuthException) {
            $statusCode = 400;
            $message = $exception->getMessage();
        } elseif ($exception instanceof HttpExceptionInterface) {
            $statusCode = $exception->getStatusCode();
            $message = $exception->getMessage();
        }

        // Log server errors
        if ($statusCode >= 500) {
            $this->logger->error($exception->getMessage(), [
                'exception' => $exception,
            ]);
        }

        return new JsonResponse([
            'error' => $message,
        ], $statusCode);
    }
}
```

Register the listener:

```yaml
# config/services.yaml
services:
    App\EventListener\ExceptionListener:
        tags:
            - { name: kernel.event_listener, event: kernel.exception }
```

---

## Validation

### Request Validation

```php
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[Route('/auth/register', methods: ['POST'])]
public function register(Request $request, ValidatorInterface $validator): JsonResponse
{
    $data = json_decode($request->getContent(), true);

    $constraints = new Assert\Collection([
        'email' => [
            new Assert\NotBlank(),
            new Assert\Email(),
        ],
        'password' => [
            new Assert\NotBlank(),
            new Assert\Length(['min' => 8]),
        ],
        'name' => new Assert\Optional([
            new Assert\Length(['max' => 255]),
        ]),
    ]);

    $errors = $validator->validate($data, $constraints);

    if (count($errors) > 0) {
        $messages = [];
        foreach ($errors as $error) {
            $messages[$error->getPropertyPath()] = $error->getMessage();
        }
        return $this->json(['errors' => $messages], 422);
    }

    // Continue with registration...
}
```

### Custom Validation Response

```json
{
  "errors": {
    "[email]": "This value is not a valid email address.",
    "[password]": "This value is too short. It should have 8 characters or more."
  }
}
```

---

## Frontend Error Handling

### Axios Interceptor

```typescript
import axios from 'axios';

const api = axios.create({
  baseURL: 'http://localhost:8000',
});

api.interceptors.response.use(
  (response) => response,
  (error) => {
    const { response } = error;

    if (response) {
      switch (response.status) {
        case 401:
          // Handle authentication errors
          if (response.data.error === 'Token expired') {
            // Try to refresh token
            return refreshAndRetry(error.config);
          }
          // Redirect to login
          window.location.href = '/login';
          break;

        case 403:
          // Handle forbidden
          showNotification('Access denied', 'error');
          break;

        case 422:
          // Handle validation errors
          return Promise.reject(response.data.errors);

        case 429:
          // Handle rate limiting
          showNotification('Too many requests. Please wait.', 'warning');
          break;

        case 500:
          // Handle server errors
          showNotification('Server error. Please try again.', 'error');
          break;
      }
    }

    return Promise.reject(error);
  }
);
```

### React Error Boundary

```tsx
import { Component, ReactNode } from 'react';

interface Props {
  children: ReactNode;
}

interface State {
  hasError: boolean;
  error?: Error;
}

class ErrorBoundary extends Component<Props, State> {
  state: State = { hasError: false };

  static getDerivedStateFromError(error: Error): State {
    return { hasError: true, error };
  }

  componentDidCatch(error: Error, errorInfo: React.ErrorInfo) {
    console.error('Auth error:', error, errorInfo);
  }

  render() {
    if (this.state.hasError) {
      return (
        <div className="error-container">
          <h2>Something went wrong</h2>
          <p>{this.state.error?.message}</p>
          <button onClick={() => window.location.reload()}>
            Try again
          </button>
        </div>
      );
    }

    return this.props.children;
  }
}
```

---

## Logging Errors

### Configure Logging

```yaml
# config/packages/monolog.yaml
monolog:
    channels:
        - better_auth

    handlers:
        better_auth:
            type: stream
            path: "%kernel.logs_dir%/better_auth.log"
            level: warning
            channels: ["better_auth"]

        better_auth_errors:
            type: stream
            path: "%kernel.logs_dir%/better_auth_errors.log"
            level: error
            channels: ["better_auth"]
```

### Log Authentication Errors

```php
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class AuthController
{
    public function __construct(
        #[Autowire(service: 'monolog.logger.better_auth')]
        private LoggerInterface $logger
    ) {}

    public function login(Request $request): JsonResponse
    {
        try {
            // ... login logic
        } catch (\Exception $e) {
            $this->logger->error('Login failed', [
                'email' => $data['email'] ?? null,
                'ip' => $request->getClientIp(),
                'error' => $e->getMessage(),
            ]);

            return $this->json(['error' => $e->getMessage()], 401);
        }
    }
}
```

---

## Next Steps

- [Security](11-SECURITY.md)
- [Troubleshooting](13-TROUBLESHOOTING.md)
- [API Reference](09-API-REFERENCE.md)
