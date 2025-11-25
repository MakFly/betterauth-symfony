# Testing

Guide to testing authentication flows in your application.

## Unit Testing

### Test Setup

```php
<?php

namespace App\Tests;

use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use BetterAuth\Core\AuthManager;

class AuthManagerTest extends KernelTestCase
{
    private AuthManager $authManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = static::getContainer();
        $this->authManager = $container->get(AuthManager::class);
    }

    public function testUserRegistration(): void
    {
        $user = $this->authManager->signUp(
            'test@example.com',
            'SecurePassword123',
            ['name' => 'Test User']
        );

        $this->assertNotNull($user->getId());
        $this->assertEquals('test@example.com', $user->getEmail());
    }
}
```

### Test User Authentication

```php
public function testUserLogin(): void
{
    // Create user first
    $this->authManager->signUp('login@example.com', 'Password123');

    // Test login
    $result = $this->authManager->signIn(
        'login@example.com',
        'Password123',
        '127.0.0.1',
        'PHPUnit Test'
    );

    $this->assertArrayHasKey('access_token', $result);
    $this->assertArrayHasKey('refresh_token', $result);
    $this->assertArrayHasKey('user', $result);
}
```

### Test Token Verification

```php
public function testTokenVerification(): void
{
    // Create and login user
    $this->authManager->signUp('token@example.com', 'Password123');
    $result = $this->authManager->signIn(
        'token@example.com',
        'Password123',
        '127.0.0.1',
        'PHPUnit Test'
    );

    // Verify token
    $user = $this->authManager->getCurrentUser($result['access_token']);

    $this->assertNotNull($user);
    $this->assertEquals('token@example.com', $user->getEmail());
}
```

---

## Functional Testing

### API Test Case

```php
<?php

namespace App\Tests\Api;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class AuthControllerTest extends WebTestCase
{
    public function testRegister(): void
    {
        $client = static::createClient();

        $client->request('POST', '/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'new@example.com',
            'password' => 'SecurePassword123',
            'name' => 'New User',
        ]));

        $this->assertResponseStatusCodeSame(201);

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('access_token', $response);
        $this->assertArrayHasKey('user', $response);
    }

    public function testLogin(): void
    {
        $client = static::createClient();

        // Register first
        $client->request('POST', '/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'login@example.com',
            'password' => 'SecurePassword123',
        ]));

        // Login
        $client->request('POST', '/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'login@example.com',
            'password' => 'SecurePassword123',
        ]));

        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertArrayHasKey('access_token', $response);
    }

    public function testProtectedRoute(): void
    {
        $client = static::createClient();

        // Register and login
        $client->request('POST', '/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'protected@example.com',
            'password' => 'SecurePassword123',
        ]));

        $loginResponse = json_decode($client->getResponse()->getContent(), true);
        $token = $loginResponse['access_token'];

        // Access protected route
        $client->request('GET', '/auth/me', [], [], [
            'HTTP_AUTHORIZATION' => 'Bearer ' . $token,
        ]);

        $this->assertResponseIsSuccessful();

        $response = json_decode($client->getResponse()->getContent(), true);
        $this->assertEquals('protected@example.com', $response['email']);
    }

    public function testUnauthorizedAccess(): void
    {
        $client = static::createClient();

        $client->request('GET', '/auth/me');

        $this->assertResponseStatusCodeSame(401);
    }

    public function testInvalidCredentials(): void
    {
        $client = static::createClient();

        $client->request('POST', '/auth/login', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => 'nonexistent@example.com',
            'password' => 'WrongPassword',
        ]));

        $this->assertResponseStatusCodeSame(401);
    }
}
```

---

## Test Database Setup

### PHPUnit Configuration

```xml
<!-- phpunit.xml.dist -->
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true">
    <php>
        <env name="APP_ENV" value="test"/>
        <env name="DATABASE_URL" value="sqlite:///%kernel.project_dir%/var/test.db"/>
    </php>
    <testsuites>
        <testsuite name="Project Test Suite">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

### Test Bootstrap

```php
<?php
// tests/bootstrap.php

use Symfony\Component\Dotenv\Dotenv;

require dirname(__DIR__).'/vendor/autoload.php';

if (file_exists(dirname(__DIR__).'/config/bootstrap.php')) {
    require dirname(__DIR__).'/config/bootstrap.php';
} elseif (method_exists(Dotenv::class, 'bootEnv')) {
    (new Dotenv())->bootEnv(dirname(__DIR__).'/.env');
}

// Create test database
passthru('php bin/console doctrine:database:create --env=test --if-not-exists');
passthru('php bin/console doctrine:migrations:migrate --env=test --no-interaction');
```

---

## Mocking

### Mock AuthManager

```php
<?php

namespace App\Tests;

use BetterAuth\Core\AuthManager;
use BetterAuth\Core\Entities\User;
use PHPUnit\Framework\TestCase;

class ServiceWithAuthTest extends TestCase
{
    public function testServiceWithMockedAuth(): void
    {
        $mockUser = $this->createMock(User::class);
        $mockUser->method('getId')->willReturn('test-user-id');
        $mockUser->method('getEmail')->willReturn('mock@example.com');

        $authManager = $this->createMock(AuthManager::class);
        $authManager->method('getCurrentUser')
            ->willReturn($mockUser);

        // Use mock in your service
        $service = new YourService($authManager);
        $result = $service->doSomething();

        $this->assertNotNull($result);
    }
}
```

### Mock Token

```php
public function testWithMockedToken(): void
{
    $authManager = $this->createMock(AuthManager::class);
    $authManager->method('signIn')
        ->willReturn([
            'access_token' => 'mock-access-token',
            'refresh_token' => 'mock-refresh-token',
            'user' => $this->createMock(User::class),
        ]);

    // Test your code
}
```

---

## Integration Testing

### Docker Test Environment

```yaml
# docker-compose.test.yml
version: '3.8'

services:
  test-db:
    image: postgres:15
    environment:
      POSTGRES_DB: test_db
      POSTGRES_USER: test
      POSTGRES_PASSWORD: test

  test-app:
    build: .
    depends_on:
      - test-db
    environment:
      DATABASE_URL: postgresql://test:test@test-db:5432/test_db
      APP_ENV: test
    command: vendor/bin/phpunit
```

### GitHub Actions

```yaml
# .github/workflows/tests.yml
name: Tests

on: [push, pull_request]

jobs:
  test:
    runs-on: ubuntu-latest

    services:
      postgres:
        image: postgres:15
        env:
          POSTGRES_PASSWORD: postgres
        ports:
          - 5432:5432

    steps:
      - uses: actions/checkout@v4

      - name: Setup PHP
        uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          extensions: pdo_pgsql

      - name: Install dependencies
        run: composer install --prefer-dist

      - name: Run tests
        env:
          DATABASE_URL: postgresql://postgres:postgres@localhost:5432/test
        run: |
          php bin/console doctrine:database:create --env=test
          php bin/console doctrine:migrations:migrate --env=test --no-interaction
          vendor/bin/phpunit
```

---

## Test Traits

### AuthenticatedTestTrait

```php
<?php

namespace App\Tests;

trait AuthenticatedTestTrait
{
    protected function createAuthenticatedClient(string $email = 'test@example.com'): \Symfony\Bundle\FrameworkBundle\KernelBrowser
    {
        $client = static::createClient();

        // Register and login
        $client->request('POST', '/auth/register', [], [], [
            'CONTENT_TYPE' => 'application/json',
        ], json_encode([
            'email' => $email,
            'password' => 'TestPassword123',
        ]));

        $response = json_decode($client->getResponse()->getContent(), true);
        $token = $response['access_token'];

        $client->setServerParameter('HTTP_AUTHORIZATION', 'Bearer ' . $token);

        return $client;
    }
}
```

### Usage

```php
class MyTest extends WebTestCase
{
    use AuthenticatedTestTrait;

    public function testProtectedEndpoint(): void
    {
        $client = $this->createAuthenticatedClient();

        $client->request('GET', '/api/protected');

        $this->assertResponseIsSuccessful();
    }
}
```

---

## Performance Testing

### Load Testing with k6

```javascript
// k6-auth-test.js
import http from 'k6/http';
import { check, sleep } from 'k6';

export let options = {
  vus: 10,
  duration: '30s',
};

export default function () {
  // Register
  let registerRes = http.post('http://localhost:8000/auth/register', JSON.stringify({
    email: `user${__VU}_${__ITER}@example.com`,
    password: 'TestPassword123',
  }), {
    headers: { 'Content-Type': 'application/json' },
  });

  check(registerRes, {
    'register status is 201': (r) => r.status === 201,
  });

  let token = JSON.parse(registerRes.body).access_token;

  // Access protected route
  let meRes = http.get('http://localhost:8000/auth/me', {
    headers: { 'Authorization': `Bearer ${token}` },
  });

  check(meRes, {
    'me status is 200': (r) => r.status === 200,
  });

  sleep(1);
}
```

---

## Next Steps

- [Troubleshooting](13-TROUBLESHOOTING.md)
- [Security](11-SECURITY.md)
- [API Reference](09-API-REFERENCE.md)
