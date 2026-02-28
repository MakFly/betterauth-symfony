<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\Tests\Functional;

use BetterAuth\Symfony\Tests\App\TestKernel;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Liip\TestFixturesBundle\Services\DatabaseToolCollection;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Base class for functional tests using the TestKernel with SQLite.
 */
abstract class AbstractFunctionalTest extends WebTestCase
{
    protected KernelBrowser $client;

    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    protected function setUp(): void
    {
        $this->client = static::createClient();

        // Create schema in SQLite test database
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $schemaTool = new SchemaTool($em);
        $metadata = $em->getMetadataFactory()->getAllMetadata();
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
    }

    protected function tearDown(): void
    {
        parent::tearDown();
    }

    /**
     * Make a JSON POST request.
     */
    protected function postJson(string $uri, array $data, array $headers = []): void
    {
        $serverHeaders = ['CONTENT_TYPE' => 'application/json'];
        foreach ($headers as $key => $value) {
            $serverHeaders['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        $this->client->request(
            method: 'POST',
            uri: $uri,
            server: $serverHeaders,
            content: json_encode($data),
        );
    }

    /**
     * Make a JSON GET request.
     */
    protected function getJson(string $uri, array $headers = []): void
    {
        $serverHeaders = ['CONTENT_TYPE' => 'application/json'];
        foreach ($headers as $key => $value) {
            $serverHeaders['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        $this->client->request(
            method: 'GET',
            uri: $uri,
            server: $serverHeaders,
        );
    }

    /**
     * Make a JSON DELETE request.
     */
    protected function deleteJson(string $uri, array $headers = []): void
    {
        $serverHeaders = ['CONTENT_TYPE' => 'application/json'];
        foreach ($headers as $key => $value) {
            $serverHeaders['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
        }

        $this->client->request(
            method: 'DELETE',
            uri: $uri,
            server: $serverHeaders,
        );
    }

    /**
     * Get decoded JSON from last response.
     */
    protected function getResponseData(): array
    {
        return json_decode($this->client->getResponse()->getContent(), true) ?? [];
    }

    /**
     * Assert the last response has the given status code.
     */
    protected function assertStatusCode(int $code): void
    {
        $this->assertResponseStatusCodeSame($code);
    }

    /**
     * Register a user and return the tokens.
     */
    protected function registerUser(string $email = 'test@example.com', string $password = 'password123'): array
    {
        $this->postJson('/auth/register', ['email' => $email, 'password' => $password]);
        $this->assertStatusCode(201);
        return $this->getResponseData();
    }

    /**
     * Login and return the tokens.
     */
    protected function loginUser(string $email = 'test@example.com', string $password = 'password123'): array
    {
        $this->postJson('/auth/login', ['email' => $email, 'password' => $password]);
        $this->assertStatusCode(200);
        return $this->getResponseData();
    }

    /**
     * Get the Bearer authorization header.
     */
    protected function bearerHeaders(string $accessToken): array
    {
        return ['Authorization' => 'Bearer ' . $accessToken];
    }
}
