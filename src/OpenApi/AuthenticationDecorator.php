<?php

declare(strict_types=1);

namespace BetterAuth\Symfony\OpenApi;

use ApiPlatform\OpenApi\Factory\OpenApiFactoryInterface;
use ApiPlatform\OpenApi\Model\Operation;
use ApiPlatform\OpenApi\Model\PathItem;
use ApiPlatform\OpenApi\Model\RequestBody;
use ApiPlatform\OpenApi\OpenApi;
use ArrayObject;

/**
 * Decorator to add BetterAuth authentication endpoints to OpenAPI documentation.
 *
 * This decorator automatically adds all BetterAuth authentication endpoints
 * to the API Platform OpenAPI specification, including:
 * - Register/Login endpoints
 * - Token refresh and logout
 * - OAuth provider endpoints
 * - Bearer authentication scheme (Paseto V4)
 */
final class AuthenticationDecorator implements OpenApiFactoryInterface
{
    public function __construct(
        private readonly OpenApiFactoryInterface $decorated
    ) {
    }

    public function __invoke(array $context = []): OpenApi
    {
        $openApi = ($this->decorated)($context);

        // Add Bearer authentication security scheme
        $components = $openApi->getComponents();
        $securitySchemes = $components->getSecuritySchemes() ?? new ArrayObject();
        $securitySchemes['bearerAuth'] = [
            'type' => 'http',
            'scheme' => 'bearer',
            'bearerFormat' => 'Paseto V4',
            'description' => 'Enter your Paseto V4 access token',
        ];

        $components = $components->withSecuritySchemes($securitySchemes);
        $openApi = $openApi->withComponents($components);

        // Add Authentication tag
        $tags = $openApi->getTags();
        $tags[] = new \ApiPlatform\OpenApi\Model\Tag('Authentication', 'BetterAuth authentication endpoints');
        $openApi = $openApi->withTags($tags);

        // Register endpoint
        $openApi = $this->addRegisterEndpoint($openApi);

        // Login endpoint
        $openApi = $this->addLoginEndpoint($openApi);

        // Get current user endpoint
        $openApi = $this->addMeEndpoint($openApi);

        // Refresh token endpoint
        $openApi = $this->addRefreshEndpoint($openApi);

        // Logout endpoint
        $openApi = $this->addLogoutEndpoint($openApi);

        // Revoke all sessions endpoint
        $openApi = $this->addRevokeAllEndpoint($openApi);

        // OAuth endpoints
        $openApi = $this->addOAuthEndpoints($openApi);

        return $openApi;
    }

    private function addRegisterEndpoint(OpenApi $openApi): OpenApi
    {
        $pathItem = new PathItem(
            post: new Operation(
                operationId: 'authRegister',
                tags: ['Authentication'],
                summary: 'Register a new user',
                description: 'Create a new user account',
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'user@example.com'],
                                    'password' => ['type' => 'string', 'format' => 'password', 'example' => 'SecurePassword123'],
                                    'name' => ['type' => 'string', 'example' => 'John Doe', 'nullable' => true],
                                ],
                                'required' => ['email', 'password'],
                            ],
                        ],
                    ])
                ),
                responses: [
                    '201' => [
                        'description' => 'User created successfully',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'user' => [
                                            'type' => 'object',
                                            'properties' => [
                                                'id' => ['type' => 'string', 'example' => '06DB1F28JAB2JHV1VXZVTKJEHM'],
                                                'email' => ['type' => 'string', 'example' => 'user@example.com'],
                                                'name' => ['type' => 'string', 'example' => 'John Doe'],
                                                'emailVerified' => ['type' => 'boolean', 'example' => false],
                                                'createdAt' => ['type' => 'string', 'format' => 'date-time', 'example' => '2025-11-23 12:42:00'],
                                            ],
                                        ],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '400' => ['description' => 'Invalid input'],
                ],
            )
        );

        $paths = $openApi->getPaths();
        $paths->addPath('/auth/register', $pathItem);

        return $openApi->withPaths($paths);
    }

    private function addLoginEndpoint(OpenApi $openApi): OpenApi
    {
        $pathItem = new PathItem(
            post: new Operation(
                operationId: 'authLogin',
                tags: ['Authentication'],
                summary: 'Login user',
                description: 'Authenticate user and receive access & refresh tokens',
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'email' => ['type' => 'string', 'format' => 'email', 'example' => 'user@example.com'],
                                    'password' => ['type' => 'string', 'format' => 'password', 'example' => 'SecurePassword123'],
                                ],
                                'required' => ['email', 'password'],
                            ],
                        ],
                    ])
                ),
                responses: [
                    '200' => [
                        'description' => 'Login successful',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'user' => ['type' => 'object'],
                                        'access_token' => ['type' => 'string', 'example' => 'v4.local.xxx...'],
                                        'refresh_token' => ['type' => 'string', 'example' => 'xxx...'],
                                        'token_type' => ['type' => 'string', 'example' => 'Bearer'],
                                        'expires_in' => ['type' => 'integer', 'example' => 7200],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Invalid credentials'],
                ],
            )
        );

        $paths = $openApi->getPaths();
        $paths->addPath('/auth/login', $pathItem);

        return $openApi->withPaths($paths);
    }

    private function addMeEndpoint(OpenApi $openApi): OpenApi
    {
        $pathItem = new PathItem(
            get: new Operation(
                operationId: 'authMe',
                tags: ['Authentication'],
                summary: 'Get current user',
                description: 'Get current authenticated user information',
                security: [['bearerAuth' => []]],
                responses: [
                    '200' => [
                        'description' => 'User information',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'id' => ['type' => 'string'],
                                        'email' => ['type' => 'string'],
                                        'name' => ['type' => 'string'],
                                        'emailVerified' => ['type' => 'boolean'],
                                        'createdAt' => ['type' => 'string', 'format' => 'date-time'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                ],
            )
        );

        $paths = $openApi->getPaths();
        $paths->addPath('/auth/me', $pathItem);

        return $openApi->withPaths($paths);
    }

    private function addRefreshEndpoint(OpenApi $openApi): OpenApi
    {
        $pathItem = new PathItem(
            post: new Operation(
                operationId: 'authRefresh',
                tags: ['Authentication'],
                summary: 'Refresh access token',
                description: 'Refresh access token using refresh token',
                requestBody: new RequestBody(
                    content: new ArrayObject([
                        'application/json' => [
                            'schema' => [
                                'type' => 'object',
                                'properties' => [
                                    'refreshToken' => ['type' => 'string', 'example' => 'xxx...'],
                                ],
                                'required' => ['refreshToken'],
                            ],
                        ],
                    ])
                ),
                responses: [
                    '200' => [
                        'description' => 'Token refreshed',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'access_token' => ['type' => 'string'],
                                        'refresh_token' => ['type' => 'string'],
                                        'token_type' => ['type' => 'string'],
                                        'expires_in' => ['type' => 'integer'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Invalid refresh token'],
                ],
            )
        );

        $paths = $openApi->getPaths();
        $paths->addPath('/auth/refresh', $pathItem);

        return $openApi->withPaths($paths);
    }

    private function addLogoutEndpoint(OpenApi $openApi): OpenApi
    {
        $pathItem = new PathItem(
            post: new Operation(
                operationId: 'authLogout',
                tags: ['Authentication'],
                summary: 'Logout user',
                description: 'Logout current user (revoke current session)',
                security: [['bearerAuth' => []]],
                responses: [
                    '200' => [
                        'description' => 'Logout successful',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => ['type' => 'string', 'example' => 'Logged out successfully'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                ],
            )
        );

        $paths = $openApi->getPaths();
        $paths->addPath('/auth/logout', $pathItem);

        return $openApi->withPaths($paths);
    }

    private function addRevokeAllEndpoint(OpenApi $openApi): OpenApi
    {
        $pathItem = new PathItem(
            post: new Operation(
                operationId: 'authRevokeAll',
                tags: ['Authentication'],
                summary: 'Revoke all sessions',
                description: 'Revoke all refresh tokens for current user',
                security: [['bearerAuth' => []]],
                responses: [
                    '200' => [
                        'description' => 'All sessions revoked',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'message' => ['type' => 'string', 'example' => 'All sessions revoked successfully'],
                                        'count' => ['type' => 'integer', 'example' => 3],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    '401' => ['description' => 'Unauthorized'],
                ],
            )
        );

        $paths = $openApi->getPaths();
        $paths->addPath('/auth/revoke-all', $pathItem);

        return $openApi->withPaths($paths);
    }

    private function addOAuthEndpoints(OpenApi $openApi): OpenApi
    {
        $paths = $openApi->getPaths();

        // OAuth redirect
        $pathItem = new PathItem(
            get: new Operation(
                operationId: 'authOAuthRedirect',
                tags: ['Authentication', 'OAuth'],
                summary: 'Get OAuth authorization URL',
                description: 'Get OAuth provider authorization URL (Google, GitHub, etc.)',
                parameters: [
                    [
                        'name' => 'provider',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string', 'enum' => ['google', 'github', 'facebook', 'microsoft', 'discord']],
                        'example' => 'google',
                    ],
                ],
                responses: [
                    '200' => [
                        'description' => 'OAuth URL',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'url' => ['type' => 'string', 'example' => 'https://accounts.google.com/o/oauth2/auth?...'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            )
        );
        $paths->addPath('/auth/oauth/{provider}', $pathItem);

        // OAuth callback
        $pathItem = new PathItem(
            get: new Operation(
                operationId: 'authOAuthCallback',
                tags: ['Authentication', 'OAuth'],
                summary: 'OAuth callback',
                description: 'Handle OAuth provider callback with authorization code',
                parameters: [
                    [
                        'name' => 'provider',
                        'in' => 'path',
                        'required' => true,
                        'schema' => ['type' => 'string', 'enum' => ['google', 'github', 'facebook', 'microsoft', 'discord']],
                    ],
                    [
                        'name' => 'code',
                        'in' => 'query',
                        'required' => true,
                        'schema' => ['type' => 'string'],
                        'description' => 'Authorization code from OAuth provider',
                    ],
                ],
                responses: [
                    '200' => [
                        'description' => 'Authentication successful',
                        'content' => [
                            'application/json' => [
                                'schema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'user' => ['type' => 'object'],
                                        'access_token' => ['type' => 'string'],
                                        'refresh_token' => ['type' => 'string'],
                                        'token_type' => ['type' => 'string'],
                                        'expires_in' => ['type' => 'integer'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            )
        );
        $paths->addPath('/auth/oauth/{provider}/callback', $pathItem);

        return $openApi->withPaths($paths);
    }
}
