# Events

BetterAuth dispatches Symfony events that you can listen to for customization, logging, and integrations.

## Available Events

| Event | Class | When Dispatched |
|-------|-------|-----------------|
| `better_auth.token_created` | TokenCreatedEvent | Before token signing |
| `better_auth.token_decoded` | TokenDecodedEvent | After token decoding |
| `better_auth.token_authenticated` | TokenAuthenticatedEvent | After successful authentication |
| `better_auth.token_invalid` | TokenInvalidEvent | When token validation fails |
| `better_auth.token_not_found` | TokenNotFoundEvent | When no token in request |
| `better_auth.token_expired` | TokenExpiredEvent | When token is expired |
| `better_auth.authentication_success` | AuthenticationSuccessEvent | After successful auth |
| `better_auth.authentication_failure` | AuthenticationFailureEvent | After failed auth |

---

## Event Constants

```php
namespace BetterAuth\Symfony\Event;

class BetterAuthEvents
{
    public const TOKEN_CREATED = 'better_auth.token_created';
    public const TOKEN_DECODED = 'better_auth.token_decoded';
    public const TOKEN_AUTHENTICATED = 'better_auth.token_authenticated';
    public const TOKEN_INVALID = 'better_auth.token_invalid';
    public const TOKEN_NOT_FOUND = 'better_auth.token_not_found';
    public const TOKEN_EXPIRED = 'better_auth.token_expired';
    public const AUTHENTICATION_SUCCESS = 'better_auth.authentication_success';
    public const AUTHENTICATION_FAILURE = 'better_auth.authentication_failure';
}
```

---

## Creating Event Subscribers

### Basic Subscriber

```php
<?php

namespace App\EventSubscriber;

use BetterAuth\Symfony\Event\BetterAuthEvents;
use BetterAuth\Symfony\Event\AuthenticationSuccessEvent;
use BetterAuth\Symfony\Event\AuthenticationFailureEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class AuthenticationSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private LoggerInterface $logger
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            BetterAuthEvents::AUTHENTICATION_SUCCESS => 'onAuthenticationSuccess',
            BetterAuthEvents::AUTHENTICATION_FAILURE => 'onAuthenticationFailure',
        ];
    }

    public function onAuthenticationSuccess(AuthenticationSuccessEvent $event): void
    {
        $user = $event->getUser();

        $this->logger->info('User authenticated successfully', [
            'user_id' => $user->getId(),
            'email' => $user->getEmail(),
            'ip' => $event->getRequest()->getClientIp(),
        ]);
    }

    public function onAuthenticationFailure(AuthenticationFailureEvent $event): void
    {
        $this->logger->warning('Authentication failed', [
            'reason' => $event->getException()->getMessage(),
            'ip' => $event->getRequest()->getClientIp(),
        ]);
    }
}
```

### Register as Service

```yaml
# config/services.yaml
services:
    App\EventSubscriber\AuthenticationSubscriber:
        tags:
            - { name: kernel.event_subscriber }
```

---

## Event Details

### TokenCreatedEvent

Dispatched before a token is signed. Use to add custom claims.

```php
use BetterAuth\Symfony\Event\TokenCreatedEvent;

public function onTokenCreated(TokenCreatedEvent $event): void
{
    $payload = $event->getPayload();
    $user = $event->getUser();

    // Add custom claims
    $payload['roles'] = $user->getRoles();
    $payload['tenant_id'] = $user->getTenantId();

    $event->setPayload($payload);
}
```

### TokenDecodedEvent

Dispatched after token is decoded, before validation.

```php
use BetterAuth\Symfony\Event\TokenDecodedEvent;

public function onTokenDecoded(TokenDecodedEvent $event): void
{
    $payload = $event->getPayload();

    // Validate custom claims
    if (!isset($payload['tenant_id'])) {
        throw new \Exception('Invalid token: missing tenant_id');
    }
}
```

### TokenAuthenticatedEvent

Dispatched after successful token authentication.

```php
use BetterAuth\Symfony\Event\TokenAuthenticatedEvent;

public function onTokenAuthenticated(TokenAuthenticatedEvent $event): void
{
    $user = $event->getUser();
    $token = $event->getToken();

    // Track last activity
    $this->userRepository->updateLastActive($user->getId());
}
```

### TokenInvalidEvent

Dispatched when token validation fails (signature, format, etc.).

```php
use BetterAuth\Symfony\Event\TokenInvalidEvent;

public function onTokenInvalid(TokenInvalidEvent $event): void
{
    $this->logger->warning('Invalid token detected', [
        'reason' => $event->getReason(),
        'ip' => $event->getRequest()->getClientIp(),
        'user_agent' => $event->getRequest()->headers->get('User-Agent'),
    ]);

    // Alert on potential attack
    if ($this->isAttackPattern($event)) {
        $this->alertService->sendSecurityAlert($event);
    }
}
```

### TokenExpiredEvent

Dispatched when an expired token is used.

```php
use BetterAuth\Symfony\Event\TokenExpiredEvent;

public function onTokenExpired(TokenExpiredEvent $event): void
{
    // Could redirect to refresh flow
    $this->logger->info('Token expired', [
        'expired_at' => $event->getExpiredAt(),
    ]);
}
```

### TokenNotFoundEvent

Dispatched when request has no token.

```php
use BetterAuth\Symfony\Event\TokenNotFoundEvent;

public function onTokenNotFound(TokenNotFoundEvent $event): void
{
    // Track anonymous access attempts
    $path = $event->getRequest()->getPathInfo();

    $this->logger->debug('No token provided', [
        'path' => $path,
        'ip' => $event->getRequest()->getClientIp(),
    ]);
}
```

### AuthenticationSuccessEvent

Dispatched after successful authentication.

```php
use BetterAuth\Symfony\Event\AuthenticationSuccessEvent;

public function onAuthSuccess(AuthenticationSuccessEvent $event): void
{
    $user = $event->getUser();
    $response = $event->getResponse();

    // Add custom data to response
    $data = json_decode($response->getContent(), true);
    $data['permissions'] = $this->permissionService->getForUser($user);
    $response->setContent(json_encode($data));

    // Track login
    $this->analyticsService->trackLogin($user, $event->getRequest());
}
```

### AuthenticationFailureEvent

Dispatched after failed authentication.

```php
use BetterAuth\Symfony\Event\AuthenticationFailureEvent;

public function onAuthFailure(AuthenticationFailureEvent $event): void
{
    $exception = $event->getException();
    $request = $event->getRequest();

    // Rate limiting
    $this->rateLimiter->hit($request->getClientIp());

    // Alert on brute force
    if ($this->rateLimiter->isExceeded($request->getClientIp())) {
        $this->alertService->sendBruteForceAlert($request->getClientIp());
    }
}
```

---

## Common Use Cases

### Audit Logging

```php
<?php

namespace App\EventSubscriber;

use BetterAuth\Symfony\Event\BetterAuthEvents;
use BetterAuth\Symfony\Event\AuthenticationSuccessEvent;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\AuditLog;

class AuditLogSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private EntityManagerInterface $em
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            BetterAuthEvents::AUTHENTICATION_SUCCESS => 'logAuth',
        ];
    }

    public function logAuth(AuthenticationSuccessEvent $event): void
    {
        $log = new AuditLog();
        $log->setUserId($event->getUser()->getId());
        $log->setAction('login');
        $log->setIpAddress($event->getRequest()->getClientIp());
        $log->setUserAgent($event->getRequest()->headers->get('User-Agent'));
        $log->setCreatedAt(new \DateTime());

        $this->em->persist($log);
        $this->em->flush();
    }
}
```

### Custom Token Claims

```php
<?php

namespace App\EventSubscriber;

use BetterAuth\Symfony\Event\BetterAuthEvents;
use BetterAuth\Symfony\Event\TokenCreatedEvent;

class TokenClaimsSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            BetterAuthEvents::TOKEN_CREATED => ['onTokenCreated', 0],
        ];
    }

    public function onTokenCreated(TokenCreatedEvent $event): void
    {
        $user = $event->getUser();
        $payload = $event->getPayload();

        // Add custom claims
        $payload['organization_id'] = $user->getOrganizationId();
        $payload['roles'] = $user->getRoles();
        $payload['permissions'] = $user->getPermissions();

        $event->setPayload($payload);
    }
}
```

### Security Alerts

```php
<?php

namespace App\EventSubscriber;

use BetterAuth\Symfony\Event\BetterAuthEvents;
use BetterAuth\Symfony\Event\AuthenticationFailureEvent;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class SecurityAlertSubscriber implements EventSubscriberInterface
{
    private array $failedAttempts = [];

    public function __construct(
        private MailerInterface $mailer
    ) {}

    public static function getSubscribedEvents(): array
    {
        return [
            BetterAuthEvents::AUTHENTICATION_FAILURE => 'onAuthFailure',
        ];
    }

    public function onAuthFailure(AuthenticationFailureEvent $event): void
    {
        $ip = $event->getRequest()->getClientIp();

        // Track failures per IP
        $this->failedAttempts[$ip] = ($this->failedAttempts[$ip] ?? 0) + 1;

        // Alert after 5 failures
        if ($this->failedAttempts[$ip] >= 5) {
            $this->sendAlert($ip, $event);
        }
    }

    private function sendAlert(string $ip, AuthenticationFailureEvent $event): void
    {
        $email = (new Email())
            ->to('security@example.com')
            ->subject('Security Alert: Multiple failed login attempts')
            ->text(sprintf(
                "Multiple failed login attempts detected.\nIP: %s\nAttempts: %d",
                $ip,
                $this->failedAttempts[$ip]
            ));

        $this->mailer->send($email);
    }
}
```

---

## Event Priority

Control execution order with priority:

```php
public static function getSubscribedEvents(): array
{
    return [
        BetterAuthEvents::TOKEN_CREATED => [
            ['addBasicClaims', 100],   // Runs first
            ['addPermissions', 50],     // Runs second
            ['logTokenCreation', -100], // Runs last
        ],
    ];
}
```

Higher priority = earlier execution.

---

## Next Steps

- [API Reference](09-API-REFERENCE.md)
- [Error Handling](10-ERROR-HANDLING.md)
- [Security](11-SECURITY.md)
