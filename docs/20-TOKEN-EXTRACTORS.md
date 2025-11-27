# Token Extractors

BetterAuth provides a flexible token extraction system similar to LexikJWTAuthenticationBundle. Extractors are responsible for retrieving authentication tokens from HTTP requests.

## Overview

Token extractors implement `TokenExtractorInterface` and are used by `BetterAuthAuthenticator` to find tokens in requests. Multiple extractors can be chained together using `ChainTokenExtractor`.

---

## Available Extractors

### AuthorizationHeaderTokenExtractor

Extracts tokens from the `Authorization` header with a configurable prefix (default: `Bearer`).

**Features:**
- Case-insensitive Bearer scheme matching (RFC 7235 compliant)
- Configurable prefix and header name
- Default extractor used by `BetterAuthAuthenticator`

**Example:**
```php
use BetterAuth\Symfony\TokenExtractor\AuthorizationHeaderTokenExtractor;

// Default: extracts from "Authorization: Bearer <token>"
$extractor = new AuthorizationHeaderTokenExtractor();

// Custom prefix
$extractor = new AuthorizationHeaderTokenExtractor('Token');

// Custom header name
$extractor = new AuthorizationHeaderTokenExtractor('Bearer', 'X-Auth-Token');
```

**Supported formats:**
- `Authorization: Bearer <token>` ✅
- `Authorization: bearer <token>` ✅ (case-insensitive)
- `Authorization: BEARER <token>` ✅ (case-insensitive)
- `Authorization: BeArEr <token>` ✅ (case-insensitive)

### CookieTokenExtractor

Extracts tokens from HTTP cookies.

**Example:**
```php
use BetterAuth\Symfony\TokenExtractor\CookieTokenExtractor;

// Default: extracts from "access_token" cookie
$extractor = new CookieTokenExtractor();

// Custom cookie name
$extractor = new CookieTokenExtractor('auth_token');
```

### QueryParameterTokenExtractor

Extracts tokens from URL query parameters.

**Example:**
```php
use BetterAuth\Symfony\TokenExtractor\QueryParameterTokenExtractor;

// Default: extracts from ?bearer=<token>
$extractor = new QueryParameterTokenExtractor();

// Custom parameter name
$extractor = new QueryParameterTokenExtractor('token');
```

### ChainTokenExtractor

Combines multiple extractors. Tries each extractor in order until one returns a token.

**Example:**
```php
use BetterAuth\Symfony\TokenExtractor\ChainTokenExtractor;
use BetterAuth\Symfony\TokenExtractor\AuthorizationHeaderTokenExtractor;
use BetterAuth\Symfony\TokenExtractor\CookieTokenExtractor;

$chain = new ChainTokenExtractor([
    new AuthorizationHeaderTokenExtractor(), // Try header first
    new CookieTokenExtractor(),               // Then cookie
]);

// Or add extractors dynamically
$chain = new ChainTokenExtractor();
$chain->addExtractor(new AuthorizationHeaderTokenExtractor());
$chain->addExtractor(new CookieTokenExtractor());
```

---

## Configuration

### Default Configuration

By default, BetterAuth uses a `ChainTokenExtractor` with `AuthorizationHeaderTokenExtractor` and `CookieTokenExtractor`:

```yaml
# config/services.yaml
services:
    BetterAuth\Symfony\TokenExtractor\AuthorizationHeaderTokenExtractor:
        arguments:
            $prefix: 'Bearer'
            $name: 'Authorization'
        public: true

    BetterAuth\Symfony\TokenExtractor\CookieTokenExtractor:
        arguments:
            $name: 'access_token'
        public: true

    BetterAuth\Symfony\TokenExtractor\ChainTokenExtractor:
        arguments:
            $extractors:
                - '@BetterAuth\Symfony\TokenExtractor\AuthorizationHeaderTokenExtractor'
                - '@BetterAuth\Symfony\TokenExtractor\CookieTokenExtractor'
        public: true

    BetterAuth\Symfony\TokenExtractor\TokenExtractorInterface:
        alias: BetterAuth\Symfony\TokenExtractor\ChainTokenExtractor
```

### Custom Configuration

#### Use Only Authorization Header

```yaml
# config/services.yaml
services:
    BetterAuth\Symfony\TokenExtractor\TokenExtractorInterface:
        alias: BetterAuth\Symfony\TokenExtractor\AuthorizationHeaderTokenExtractor
```

#### Custom Chain Order

```yaml
# config/services.yaml
services:
    BetterAuth\Symfony\TokenExtractor\ChainTokenExtractor:
        arguments:
            $extractors:
                - '@BetterAuth\Symfony\TokenExtractor\QueryParameterTokenExtractor'
                - '@BetterAuth\Symfony\TokenExtractor\AuthorizationHeaderTokenExtractor'
                - '@BetterAuth\Symfony\TokenExtractor\CookieTokenExtractor'
```

#### Custom Cookie Name

```yaml
# config/services.yaml
services:
    BetterAuth\Symfony\TokenExtractor\CookieTokenExtractor:
        arguments:
            $name: 'BEARER'  # Same as LexikJWT default
```

---

## Creating Custom Extractors

### Implementing TokenExtractorInterface

```php
<?php

namespace App\TokenExtractor;

use BetterAuth\Symfony\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\HttpFoundation\Request;

class CustomHeaderTokenExtractor implements TokenExtractorInterface
{
    public function extract(Request $request): ?string
    {
        // Extract from custom header
        $token = $request->headers->get('X-Custom-Auth-Token');
        
        if ($token && str_starts_with($token, 'Custom ')) {
            return substr($token, 7); // Remove "Custom " prefix
        }
        
        return null;
    }
}
```

### Register Custom Extractor

```yaml
# config/services.yaml
services:
    App\TokenExtractor\CustomHeaderTokenExtractor:
        public: true

    BetterAuth\Symfony\TokenExtractor\ChainTokenExtractor:
        arguments:
            $extractors:
                - '@BetterAuth\Symfony\TokenExtractor\AuthorizationHeaderTokenExtractor'
                - '@App\TokenExtractor\CustomHeaderTokenExtractor'
```

### Use Custom Extractor in Authenticator

```yaml
# config/services.yaml
services:
    BetterAuth\Symfony\Security\BetterAuthAuthenticator:
        arguments:
            $tokenExtractor: '@App\TokenExtractor\CustomHeaderTokenExtractor'
```

---

## Integration with Events

Token extractors work seamlessly with BetterAuth events. When a token is not found, `TokenNotFoundEvent` is dispatched:

```php
<?php

namespace App\EventSubscriber;

use BetterAuth\Symfony\Event\BetterAuthEvents;
use BetterAuth\Symfony\Event\TokenNotFoundEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TokenNotFoundSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            BetterAuthEvents::TOKEN_NOT_FOUND => 'onTokenNotFound',
        ];
    }

    public function onTokenNotFound(TokenNotFoundEvent $event): void
    {
        // Log missing token attempts
        $request = $event->getRequest();
        
        // Optionally set a custom response
        // $response = new JsonResponse(['error' => 'Token required'], 401);
        // $event->setResponse($response);
    }
}
```

### Event Flow

1. **Token Extraction**: `BetterAuthAuthenticator` calls `$tokenExtractor->extract($request)`
2. **Token Not Found**: If `extract()` returns `null`, `TokenNotFoundEvent` is dispatched
3. **Token Decoded**: If token is found, it's decoded and `TokenDecodedEvent` is dispatched
4. **Token Authenticated**: After validation, `TokenAuthenticatedEvent` is dispatched

---

## Advanced Use Cases

### Multi-Source Token Support

Support tokens from multiple sources with priority:

```php
use BetterAuth\Symfony\TokenExtractor\ChainTokenExtractor;
use BetterAuth\Symfony\TokenExtractor\AuthorizationHeaderTokenExtractor;
use BetterAuth\Symfony\TokenExtractor\QueryParameterTokenExtractor;
use BetterAuth\Symfony\TokenExtractor\CookieTokenExtractor;

$chain = new ChainTokenExtractor([
    new AuthorizationHeaderTokenExtractor(), // Priority 1: Header
    new QueryParameterTokenExtractor(),      // Priority 2: Query param
    new CookieTokenExtractor(),              // Priority 3: Cookie (fallback)
]);
```

### API Versioning with Extractors

Different extractors for different API versions:

```php
<?php

namespace App\TokenExtractor;

use BetterAuth\Symfony\TokenExtractor\TokenExtractorInterface;
use Symfony\Component\HttpFoundation\Request;

class ApiVersionTokenExtractor implements TokenExtractorInterface
{
    public function __construct(
        private TokenExtractorInterface $v1Extractor,
        private TokenExtractorInterface $v2Extractor,
    ) {}

    public function extract(Request $request): ?string
    {
        $path = $request->getPathInfo();
        
        if (str_starts_with($path, '/api/v1/')) {
            return $this->v1Extractor->extract($request);
        }
        
        if (str_starts_with($path, '/api/v2/')) {
            return $this->v2Extractor->extract($request);
        }
        
        return null;
    }
}
```

### Token Decoding Events

When a token is successfully extracted and decoded, `TokenDecodedEvent` is dispatched:

```php
<?php

namespace App\EventSubscriber;

use BetterAuth\Symfony\Event\BetterAuthEvents;
use BetterAuth\Symfony\Event\TokenDecodedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class TokenDecodedSubscriber implements EventSubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            BetterAuthEvents::TOKEN_DECODED => 'onTokenDecoded',
        ];
    }

    public function onTokenDecoded(TokenDecodedEvent $event): void
    {
        $payload = $event->getPayload();
        
        // Validate custom claims
        if (!isset($payload['tenant_id'])) {
            throw new \Exception('Invalid token: missing tenant_id');
        }
        
        // Log token usage
        $this->logger->info('Token decoded', [
            'user_id' => $payload['sub'] ?? null,
            'tenant_id' => $payload['tenant_id'] ?? null,
        ]);
    }
}
```

---

## Migration from LexikJWT

### Lexik Configuration

```yaml
# config/packages/lexik_jwt_authentication.yaml
lexik_jwt_authentication:
    token_extractors:
        authorization_header:
            enabled: true
            prefix: Bearer
            name: Authorization
        cookie:
            enabled: true
            name: BEARER
        query_parameter:
            enabled: true
            name: bearer
```

### BetterAuth Equivalent

```yaml
# config/services.yaml
services:
    BetterAuth\Symfony\TokenExtractor\AuthorizationHeaderTokenExtractor:
        arguments:
            $prefix: 'Bearer'
            $name: 'Authorization'
        public: true

    BetterAuth\Symfony\TokenExtractor\CookieTokenExtractor:
        arguments:
            $name: 'BEARER'
        public: true

    BetterAuth\Symfony\TokenExtractor\QueryParameterTokenExtractor:
        arguments:
            $name: 'bearer'
        public: true

    BetterAuth\Symfony\TokenExtractor\ChainTokenExtractor:
        arguments:
            $extractors:
                - '@BetterAuth\Symfony\TokenExtractor\AuthorizationHeaderTokenExtractor'
                - '@BetterAuth\Symfony\TokenExtractor\CookieTokenExtractor'
                - '@BetterAuth\Symfony\TokenExtractor\QueryParameterTokenExtractor'

    BetterAuth\Symfony\TokenExtractor\TokenExtractorInterface:
        alias: BetterAuth\Symfony\TokenExtractor\ChainTokenExtractor
```

---

## Testing Extractors

### Unit Tests

```php
<?php

namespace App\Tests\TokenExtractor;

use BetterAuth\Symfony\TokenExtractor\AuthorizationHeaderTokenExtractor;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;

class AuthorizationHeaderTokenExtractorTest extends TestCase
{
    public function testExtractWithBearerToken(): void
    {
        $extractor = new AuthorizationHeaderTokenExtractor();
        $request = new Request();
        $request->headers->set('Authorization', 'Bearer my-token');

        $result = $extractor->extract($request);

        $this->assertSame('my-token', $result);
    }

    public function testExtractCaseInsensitive(): void
    {
        $extractor = new AuthorizationHeaderTokenExtractor();
        
        $variations = [
            'Bearer token123',
            'bearer token123',
            'BEARER token123',
            'BeArEr token123',
        ];

        foreach ($variations as $auth) {
            $request = new Request();
            $request->headers->set('Authorization', $auth);
            
            $result = $extractor->extract($request);
            $this->assertSame('token123', $result, "Failed for: $auth");
        }
    }
}
```

---

## Best Practices

1. **Use ChainTokenExtractor**: Combine multiple extractors for flexibility
2. **Header First**: Prefer Authorization header over cookies for security
3. **Case-Insensitive**: Bearer scheme matching is case-insensitive (RFC 7235)
4. **Custom Extractors**: Implement `TokenExtractorInterface` for custom logic
5. **Event Integration**: Use events to log and monitor token extraction
6. **Testing**: Always test extractors with various input formats

---

## Related Documentation

- [Events](08-EVENTS.md) - Complete event system documentation
- [Customization](19-CUSTOMIZATION.md) - Advanced customization guide
- [Migration](14-MIGRATION.md) - Migration from LexikJWT
- [Security](11-SECURITY.md) - Security best practices

---

## API Reference

### TokenExtractorInterface

```php
interface TokenExtractorInterface
{
    /**
     * Extract a token from the request.
     *
     * @return string|null The token if found, null otherwise
     */
    public function extract(Request $request): ?string;
}
```

### AuthorizationHeaderTokenExtractor

```php
final class AuthorizationHeaderTokenExtractor implements TokenExtractorInterface
{
    public function __construct(
        private readonly string $prefix = 'Bearer',
        private readonly string $name = 'Authorization',
    ) {}
}
```

### ChainTokenExtractor

```php
final class ChainTokenExtractor implements TokenExtractorInterface
{
    public function __construct(array $extractors = []);
    public function addExtractor(TokenExtractorInterface $extractor): self;
    public function getExtractors(): array;
    public function clearExtractors(): self;
}
```

